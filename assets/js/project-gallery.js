/**
 * Project Gallery JavaScript
 * Lightbox functionality and gallery interactions
 */

(function($) {
    'use strict';
    
    var ProjectGalleryJS = {
        
        currentImages: [],
        currentIndex: 0,
        lightboxOpen: false,
        
        init: function() {
            this.bindEvents();
            this.createLightbox();
        },
        
        bindEvents: function() {
            // Gallery image click
            $(document).on('click', '.gallery-image', this.openLightbox.bind(this));
            
            // Lightbox navigation
            $(document).on('click', '.lightbox-close', this.closeLightbox.bind(this));
            $(document).on('click', '.lightbox-prev', this.prevImage.bind(this));
            $(document).on('click', '.lightbox-next', this.nextImage.bind(this));
            
            // Keyboard navigation
            $(document).on('keydown', this.handleKeyboard.bind(this));
            
            // Click outside to close
            $(document).on('click', '.lightbox', function(e) {
                if (e.target === this) {
                    ProjectGalleryJS.closeLightbox();
                }
            });
            
            // Prevent page scroll when lightbox is open
            $(document).on('wheel touchmove', function(e) {
                if (ProjectGalleryJS.lightboxOpen) {
                    e.preventDefault();
                }
            });
        },
        
        createLightbox: function() {
            if ($('#project-lightbox').length) {
                return;
            }
            
            var lightboxHTML = `
                <div id="project-lightbox" class="lightbox">
                    <div class="lightbox-content">
                        <img src="" alt="" class="lightbox-image">
                        <button class="lightbox-close" aria-label="Kapat">&times;</button>
                        <button class="lightbox-nav lightbox-prev" aria-label="Önceki">&#8249;</button>
                        <button class="lightbox-nav lightbox-next" aria-label="Sonraki">&#8250;</button>
                        <div class="lightbox-counter"></div>
                    </div>
                </div>
            `;
            
            $('body').append(lightboxHTML);
        },
        
        openLightbox: function(e) {
            e.preventDefault();
            
            var $clickedImage = $(e.currentTarget);
            var $gallery = $clickedImage.closest('.single-project-gallery');
            
            if (!$gallery.length) {
                return;
            }
            
            // Get all images in the gallery
            this.currentImages = [];
            var clickedIndex = 0;
            
            $gallery.find('.gallery-image').each(function(index) {
                var $img = $(this).find('img');
                var fullSrc = $img.data('full') || $img.attr('src');
                var alt = $img.attr('alt') || '';
                
                ProjectGalleryJS.currentImages.push({
                    src: fullSrc,
                    alt: alt
                });
                
                if (this === $clickedImage[0]) {
                    clickedIndex = index;
                }
            });
            
            this.currentIndex = clickedIndex;
            this.showLightboxImage();
            this.showLightbox();
        },
        
        showLightbox: function() {
            $('#project-lightbox').addClass('active');
            $('body').addClass('lightbox-open');
            this.lightboxOpen = true;
            
            // Focus management for accessibility
            $('#project-lightbox .lightbox-close').focus();
        },
        
        closeLightbox: function() {
            $('#project-lightbox').removeClass('active');
            $('body').removeClass('lightbox-open');
            this.lightboxOpen = false;
            this.currentImages = [];
            this.currentIndex = 0;
        },
        
        showLightboxImage: function() {
            if (!this.currentImages.length) {
                return;
            }
            
            var image = this.currentImages[this.currentIndex];
            var $lightbox = $('#project-lightbox');
            var $img = $lightbox.find('.lightbox-image');
            
            // Show loading state
            $img.attr('src', '').hide();
            
            // Load new image
            var newImg = new Image();
            newImg.onload = function() {
                $img.attr('src', image.src).attr('alt', image.alt).fadeIn(300);
            };
            newImg.src = image.src;
            
            // Update counter
            var counter = (this.currentIndex + 1) + ' / ' + this.currentImages.length;
            $lightbox.find('.lightbox-counter').text(counter);
            
            // Show/hide navigation buttons
            var $prev = $lightbox.find('.lightbox-prev');
            var $next = $lightbox.find('.lightbox-next');
            
            if (this.currentImages.length <= 1) {
                $prev.hide();
                $next.hide();
            } else {
                $prev.toggle(this.currentIndex > 0);
                $next.toggle(this.currentIndex < this.currentImages.length - 1);
            }
        },
        
        prevImage: function() {
            if (this.currentIndex > 0) {
                this.currentIndex--;
                this.showLightboxImage();
            }
        },
        
        nextImage: function() {
            if (this.currentIndex < this.currentImages.length - 1) {
                this.currentIndex++;
                this.showLightboxImage();
            }
        },
        
        handleKeyboard: function(e) {
            if (!this.lightboxOpen) {
                return;
            }
            
            switch(e.keyCode) {
                case 27: // Escape
                    this.closeLightbox();
                    break;
                case 37: // Left arrow
                    this.prevImage();
                    break;
                case 39: // Right arrow
                    this.nextImage();
                    break;
            }
        }
    };
    
    // Touch/swipe support for mobile
    var TouchHandler = {
        startX: 0,
        startY: 0,
        endX: 0,
        endY: 0,
        minSwipeDistance: 50,
        
        init: function() {
            $(document).on('touchstart', '.lightbox-content', this.handleTouchStart.bind(this));
            $(document).on('touchend', '.lightbox-content', this.handleTouchEnd.bind(this));
        },
        
        handleTouchStart: function(e) {
            var touch = e.originalEvent.touches[0];
            this.startX = touch.clientX;
            this.startY = touch.clientY;
        },
        
        handleTouchEnd: function(e) {
            if (!ProjectGalleryJS.lightboxOpen) {
                return;
            }
            
            var touch = e.originalEvent.changedTouches[0];
            this.endX = touch.clientX;
            this.endY = touch.clientY;
            
            var deltaX = this.endX - this.startX;
            var deltaY = this.endY - this.startY;
            
            // Check if it's a horizontal swipe
            if (Math.abs(deltaX) > Math.abs(deltaY) && Math.abs(deltaX) > this.minSwipeDistance) {
                if (deltaX > 0) {
                    // Swipe right - previous image
                    ProjectGalleryJS.prevImage();
                } else {
                    // Swipe left - next image
                    ProjectGalleryJS.nextImage();
                }
            }
        }
    };
    
    // Image lazy loading for better performance
    var LazyLoader = {
        init: function() {
            this.observeImages();
        },
        
        observeImages: function() {
            if ('IntersectionObserver' in window) {
                var imageObserver = new IntersectionObserver(function(entries, observer) {
                    entries.forEach(function(entry) {
                        if (entry.isIntersecting) {
                            var img = entry.target;
                            var src = img.dataset.src;
                            
                            if (src) {
                                img.src = src;
                                img.removeAttribute('data-src');
                                imageObserver.unobserve(img);
                            }
                        }
                    });
                });
                
                document.querySelectorAll('img[data-src]').forEach(function(img) {
                    imageObserver.observe(img);
                });
            } else {
                // Fallback for older browsers
                $('img[data-src]').each(function() {
                    var $img = $(this);
                    $img.attr('src', $img.data('src')).removeAttr('data-src');
                });
            }
        }
    };
    
    // Smooth scrolling for anchor links
    var SmoothScroll = {
        init: function() {
            $(document).on('click', 'a[href*="#"]:not([href="#"])', function(e) {
                var target = $(this.hash);
                if (target.length) {
                    e.preventDefault();
                    $('html, body').animate({
                        scrollTop: target.offset().top - 80
                    }, 500);
                }
            });
        }
    };
    
    // Gallery grid responsive adjustments
    var ResponsiveGrid = {
        init: function() {
            this.adjustGrid();
            $(window).on('resize', this.debounce(this.adjustGrid.bind(this), 300));
        },
        
        adjustGrid: function() {
            $('.project-gallery').each(function() {
                var $gallery = $(this);
                var columns = parseInt($gallery.data('columns')) || 3;
                var width = $gallery.width();
                
                if (width < 480) {
                    columns = 1;
                } else if (width < 768) {
                    columns = Math.min(columns, 2);
                }
                
                $gallery.css('grid-template-columns', 'repeat(' + columns + ', 1fr)');
            });
        },
        
        debounce: function(func, wait) {
            var timeout;
            return function executedFunction() {
                var later = function() {
                    clearTimeout(timeout);
                    func();
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
    };
    
    // Initialize everything when document is ready
    $(document).ready(function() {
        ProjectGalleryJS.init();
        TouchHandler.init();
        LazyLoader.init();
        SmoothScroll.init();
        ResponsiveGrid.init();
        
        // Add loading class to galleries
        $('.project-gallery').addClass('project-gallery-loading');
        
        // Remove loading class after images load
        $('.project-gallery img').on('load', function() {
            $(this).closest('.project-gallery').removeClass('project-gallery-loading');
        });
        
        // Handle gallery images that might already be cached
        $('.project-gallery img').each(function() {
            if (this.complete) {
                $(this).trigger('load');
            }
        });
    });
    
    // Expose API for external use
    window.ProjectGallery = {
        openLightbox: function(images, startIndex) {
            ProjectGalleryJS.currentImages = images || [];
            ProjectGalleryJS.currentIndex = startIndex || 0;
            ProjectGalleryJS.showLightboxImage();
            ProjectGalleryJS.showLightbox();
        },
        closeLightbox: function() {
            ProjectGalleryJS.closeLightbox();
        }
    };
    
})(jQuery);