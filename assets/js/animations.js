/**
 * Project Gallery Animations JavaScript
 * Gelişmiş animasyon ve efekt sistemi
 */

(function($) {
    'use strict';
    
    var ProjectGalleryAnimations = {
        
        settings: {},
        observer: null,
        animatedElements: new Set(),
        
        init: function() {
            if (typeof projectGalleryAnimations === 'undefined') {
                return;
            }
            
            this.settings = projectGalleryAnimations.settings;
            
            if (!this.settings.enable_animations) {
                return;
            }
            
            this.bindEvents();
            this.initIntersectionObserver();
            this.initParallax();
            this.processGalleries();
        },
        
        bindEvents: function() {
            var self = this;
            
            // Window events
            $(window).on('scroll', function() {
                if (self.settings.parallax_enabled) {
                    self.updateParallax();
                }
            });
            
            $(window).on('resize', function() {
                self.updateAnimations();
            });
            
            // Gallery events
            $(document).on('mouseenter', '.project-item', function() {
                self.triggerHoverAnimation(this, 'enter');
            });
            
            $(document).on('mouseleave', '.project-item', function() {
                self.triggerHoverAnimation(this, 'leave');
            });
        },
        
        initIntersectionObserver: function() {
            if (!this.settings.use_intersection_observer || !window.IntersectionObserver) {
                this.fallbackScrollDetection();
                return;
            }
            
            var self = this;
            
            this.observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        self.triggerEntranceAnimation(entry.target);
                    } else if (self.settings.scroll_repeat) {
                        self.resetAnimation(entry.target);
                    }
                });
            }, {
                threshold: this.settings.animation_threshold,
                rootMargin: this.settings.scroll_trigger_offset
            });
        },
        
        processGalleries: function() {
            var self = this;
            
            $('.project-gallery.pgal-animated').each(function() {
                self.initGallery(this);
            });
        },
        
        initGallery: function(gallery) {
            var $gallery = $(gallery);
            var $items = $gallery.find('.project-item');
            var self = this;
            
            // Add initial states
            $items.each(function(index) {
                var $item = $(this);
                $item.addClass('pgal-animate-ready');
                
                if (self.settings.gallery_entrance_stagger > 0) {
                    var delay = index * self.settings.gallery_entrance_stagger;
                    $item.css('animation-delay', delay + 'ms');
                }
                
                // Observe for intersection
                if (self.observer) {
                    self.observer.observe(this);
                }
            });
        },
        
        triggerEntranceAnimation: function(element) {
            var $element = $(element);
            
            if (this.animatedElements.has(element)) {
                return;
            }
            
            $element.addClass('pgal-animate-in');
            this.animatedElements.add(element);
            
            // Remove loading animation if present
            $element.removeClass('pgal-loading-active');
        },
        
        triggerHoverAnimation: function(element, state) {
            var $element = $(element);
            
            if (state === 'enter') {
                $element.addClass('pgal-hover-active');
            } else {
                $element.removeClass('pgal-hover-active');
            }
        },
        
        resetAnimation: function(element) {
            var $element = $(element);
            $element.removeClass('pgal-animate-in');
            this.animatedElements.delete(element);
        },
        
        initParallax: function() {
            if (!this.settings.parallax_enabled) {
                return;
            }
            
            $('.project-gallery.pgal-parallax .project-item').each(function() {
                $(this).attr('data-parallax-speed', this.settings.parallax_speed);
            });
        },
        
        updateParallax: function() {
            if (!this.settings.parallax_enabled) {
                return;
            }
            
            var scrollTop = $(window).scrollTop();
            var windowHeight = $(window).height();
            
            $('.project-gallery.pgal-parallax .project-item').each(function() {
                var $item = $(this);
                var itemTop = $item.offset().top;
                var itemHeight = $item.outerHeight();
                var speed = parseFloat($item.data('parallax-speed') || 0.5);
                
                // Check if item is in viewport
                if (itemTop + itemHeight > scrollTop && itemTop < scrollTop + windowHeight) {
                    var yPos = -(scrollTop - itemTop) * speed;
                    $item.css('transform', 'translate3d(0, ' + yPos + 'px, 0)');
                }
            });
        },
        
        fallbackScrollDetection: function() {
            var self = this;
            
            $(window).on('scroll', function() {
                $('.pgal-animate-ready:not(.pgal-animate-in)').each(function() {
                    if (self.isElementInViewport(this)) {
                        self.triggerEntranceAnimation(this);
                    }
                });
            });
        },
        
        isElementInViewport: function(element) {
            var rect = element.getBoundingClientRect();
            var windowHeight = window.innerHeight;
            var threshold = windowHeight * this.settings.animation_threshold;
            
            return rect.top <= windowHeight - threshold && rect.bottom >= threshold;
        },
        
        updateAnimations: function() {
            // Recalculate positions on window resize
            if (this.settings.parallax_enabled) {
                this.updateParallax();
            }
        },
        
        // Loading animations
        startLoadingAnimation: function(element) {
            var $element = $(element);
            var loadingType = this.settings.loading_animation;
            
            if (loadingType === 'none') {
                return;
            }
            
            $element.addClass('pgal-loading-active pgal-loading-' + loadingType);
        },
        
        stopLoadingAnimation: function(element) {
            var $element = $(element);
            $element.removeClass('pgal-loading-active');
        },
        
        // Animation presets
        applyPreset: function(presetName) {
            if (!this.settings.animation_presets[presetName]) {
                return;
            }
            
            var preset = this.settings.animation_presets[presetName];
            
            // Apply preset settings
            Object.keys(preset).forEach(function(key) {
                this.settings[key] = preset[key];
            });
            
            // Re-initialize with new settings
            this.processGalleries();
        },
        
        // Performance optimizations
        enablePerformanceMode: function() {
            $('body').addClass('pgal-performance-mode');
            
            // Reduce animation durations
            this.settings.gallery_entrance_duration = Math.min(this.settings.gallery_entrance_duration, 300);
            this.settings.hover_duration = Math.min(this.settings.hover_duration, 200);
            
            // Disable complex effects
            this.settings.parallax_enabled = false;
        },
        
        disablePerformanceMode: function() {
            $('body').removeClass('pgal-performance-mode');
        },
        
        // Debug helpers
        debugAnimation: function(element) {
            console.log('Animation Debug:', {
                element: element,
                classes: element.className,
                inViewport: this.isElementInViewport(element),
                animated: this.animatedElements.has(element)
            });
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        ProjectGalleryAnimations.init();
        
        // Check for reduced motion preference
        if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            ProjectGalleryAnimations.enablePerformanceMode();
        }
    });
    
    // Global access
    window.ProjectGalleryAnimations = ProjectGalleryAnimations;
    
})(jQuery);