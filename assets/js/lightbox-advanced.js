/**
 * Project Gallery Advanced Lightbox JavaScript
 * Gelişmiş lightbox işlevleri: zoom, döndürme, sosyal paylaşım, EXIF
 */

(function($) {
    'use strict';
    
    var ProjectGalleryLightbox = {
        
        // Lightbox state
        isOpen: false,
        currentIndex: 0,
        images: [],
        settings: {},
        
        // Zoom ve transform state
        zoomLevel: 1,
        rotation: 0,
        translateX: 0,
        translateY: 0,
        isDragging: false,
        isZoomed: false,
        
        // Slideshow state
        slideshowActive: false,
        slideshowTimer: null,
        
        // Touch/gesture state
        hammer: null,
        lastPanData: null,
        
        // Elements
        $lightbox: null,
        $overlay: null,
        $container: null,
        $imageWrapper: null,
        $mainImage: null,
        $loading: null,
        $toolbar: null,
        
        init: function() {
            if (typeof projectGalleryLightbox === 'undefined') {
                return;
            }
            
            this.settings = projectGalleryLightbox.settings;
            this.strings = projectGalleryLightbox.strings;
            
            this.bindEvents();
            this.initElements();
            this.setupKeyboardShortcuts();
            
            // CSS custom properties ayarla
            this.updateCSSVariables();
        },
        
        bindEvents: function() {
            var self = this;
            
            // Gallery resimlerine tıklama
            $(document).on('click', '.pgal-lightbox-image', function(e) {
                e.preventDefault();
                self.openLightbox(this);
            });
            
            // Lightbox kontrolleri
            $(document).on('click', '.pgal-btn-close', function() {
                self.closeLightbox();
            });
            
            $(document).on('click', '.pgal-nav-prev .pgal-btn-nav', function() {
                self.previousImage();
            });
            
            $(document).on('click', '.pgal-nav-next .pgal-btn-nav', function() {
                self.nextImage();
            });
            
            // Zoom kontrolleri
            $(document).on('click', '.pgal-btn-zoom-in', function() {
                self.zoomIn();
            });
            
            $(document).on('click', '.pgal-btn-zoom-out', function() {
                self.zoomOut();
            });
            
            $(document).on('click', '.pgal-btn-zoom-fit', function() {
                self.zoomFit();
            });
            
            // Döndürme kontrolleri
            $(document).on('click', '.pgal-btn-rotate-left', function() {
                self.rotateLeft();
            });
            
            $(document).on('click', '.pgal-btn-rotate-right', function() {
                self.rotateRight();
            });
            
            // Diğer kontroller
            $(document).on('click', '.pgal-btn-fullscreen', function() {
                self.toggleFullscreen();
            });
            
            $(document).on('click', '.pgal-btn-slideshow', function() {
                self.toggleSlideshow();
            });
            
            $(document).on('click', '.pgal-btn-info', function() {
                self.toggleInfoPanel();
            });
            
            $(document).on('click', '.pgal-btn-share', function() {
                self.toggleSharePanel();
            });
            
            $(document).on('click', '.pgal-btn-download', function() {
                self.downloadImage();
            });
            
            $(document).on('click', '.pgal-btn-compare', function() {
                self.toggleComparisonMode();
            });
            
            $(document).on('click', '.pgal-btn-help', function() {
                self.toggleHelpPanel();
            });
            
            // Panel kapatma
            $(document).on('click', '.pgal-btn-close-info', function() {
                self.closeInfoPanel();
            });
            
            $(document).on('click', '.pgal-btn-close-share', function() {
                self.closeSharePanel();
            });
            
            $(document).on('click', '.pgal-btn-close-help', function() {
                self.closeHelpPanel();
            });
            
            // Sosyal medya paylaşımı
            $(document).on('click', '.pgal-share-btn', function() {
                var platform = $(this).data('platform');
                self.shareImage(platform);
            });
            
            // URL kopyalama
            $(document).on('click', '.pgal-btn-copy', function() {
                self.copyShareUrl();
            });
            
            // Thumbnail navigasyon
            $(document).on('click', '.pgal-thumbnail-item', function() {
                var index = $(this).data('index');
                self.goToImage(index);
            });
            
            // Overlay tıklama ile kapatma
            $(document).on('click', '.pgal-lightbox-overlay', function() {
                self.closeLightbox();
            });
            
            // Mouse wheel zoom
            if (this.settings.mouse_wheel_zoom) {
                $(document).on('wheel', '.pgal-image-wrapper', function(e) {
                    e.preventDefault();
                    if (e.originalEvent.deltaY < 0) {
                        self.zoomIn();
                    } else {
                        self.zoomOut();
                    }
                });
            }
            
            // Resim yüklenme
            $(document).on('load', '.pgal-main-image', function() {
                self.onImageLoad();
            });
            
            $(document).on('error', '.pgal-main-image', function() {
                self.onImageError();
            });
            
            // Window resize
            $(window).on('resize', function() {
                if (self.isOpen) {
                    self.updateImagePosition();
                }
            });
        },
        
        initElements: function() {
            this.$lightbox = $('#project-gallery-lightbox');
            this.$overlay = this.$lightbox.find('.pgal-lightbox-overlay');
            this.$container = this.$lightbox.find('.pgal-lightbox-container');
            this.$imageWrapper = this.$lightbox.find('.pgal-image-wrapper');
            this.$mainImage = this.$lightbox.find('.pgal-main-image');
            this.$loading = this.$lightbox.find('.pgal-loading');
            this.$toolbar = this.$lightbox.find('.pgal-lightbox-toolbar');
        },
        
        setupKeyboardShortcuts: function() {
            var self = this;
            
            if (!this.settings.enable_keyboard_nav) {
                return;
            }
            
            $(document).on('keydown', function(e) {
                if (!self.isOpen) return;
                
                switch(e.which) {
                    case 27: // ESC
                        self.closeLightbox();
                        break;
                    case 37: // Left Arrow
                        self.previousImage();
                        break;
                    case 39: // Right Arrow
                        self.nextImage();
                        break;
                    case 32: // Space
                        e.preventDefault();
                        self.toggleSlideshow();
                        break;
                    case 107: // + (Plus)
                    case 187: // = (Equals/Plus)
                        self.zoomIn();
                        break;
                    case 109: // - (Minus)
                    case 189: // - (Dash/Minus)
                        self.zoomOut();
                        break;
                    case 82: // R
                        self.rotateRight();
                        break;
                    case 70: // F
                        self.toggleFullscreen();
                        break;
                    case 73: // I
                        self.toggleInfoPanel();
                        break;
                    case 84: // T
                        self.toggleThumbnails();
                        break;
                    case 72: // H
                        self.toggleHelpPanel();
                        break;
                }
            });
        },
        
        updateCSSVariables: function() {
            var root = document.documentElement;
            root.style.setProperty('--pgal-bg-color', this.settings.background_color);
            root.style.setProperty('--pgal-bg-opacity', this.settings.background_opacity);
            root.style.setProperty('--pgal-border-radius', this.settings.border_radius + 'px');
            root.style.setProperty('--pgal-animation-duration', this.settings.animation_duration + 'ms');
        },
        
        openLightbox: function(imageElement) {
            var $galleryContainer = $(imageElement).closest('.project-gallery');
            this.images = this.collectGalleryImages($galleryContainer);
            
            if (this.images.length === 0) {
                return;
            }
            
            // Tıklanan resmin index'ini bul
            var clickedSrc = $(imageElement).attr('src');
            this.currentIndex = this.findImageIndex(clickedSrc);
            
            this.isOpen = true;
            this.showLightbox();
            this.loadCurrentImage();
            this.updateInterface();
            this.setupTouchGestures();
            
            // Body scroll'u engelle
            $('body').addClass('pgal-lightbox-open');
            
            // Analytics
            this.trackEvent('lightbox_open', {
                image_index: this.currentIndex,
                total_images: this.images.length
            });
        },
        
        collectGalleryImages: function($container) {
            var images = [];
            
            $container.find('.pgal-lightbox-image').each(function() {
                var $img = $(this);
                var imageData = {
                    src: $img.attr('src'),
                    full_src: $img.data('full-src') || $img.attr('src'),
                    title: $img.attr('alt') || $img.attr('title') || '',
                    description: $img.data('description') || '',
                    attachment_id: $img.data('attachment-id') || 0,
                    project_id: $img.data('project-id') || 0
                };
                images.push(imageData);
            });
            
            return images;
        },
        
        findImageIndex: function(src) {
            for (var i = 0; i < this.images.length; i++) {
                if (this.images[i].src === src || this.images[i].full_src === src) {
                    return i;
                }
            }
            return 0;
        },
        
        showLightbox: function() {
            this.$lightbox.fadeIn(this.settings.animation_duration);
            
            // Lightbox'ı animate et
            this.$container.css({
                transform: 'scale(0.8)',
                opacity: 0
            }).animate({
                opacity: 1
            }, {
                duration: this.settings.animation_duration,
                step: function(now) {
                    var scale = 0.8 + (0.2 * now);
                    $(this).css('transform', 'scale(' + scale + ')');
                }
            });
        },
        
        closeLightbox: function() {
            var self = this;
            
            this.isOpen = false;
            this.stopSlideshow();
            this.closeAllPanels();
            
            // Touch gesture'ları temizle
            if (this.hammer) {
                this.hammer.destroy();
                this.hammer = null;
            }
            
            // Animate out
            this.$container.animate({
                opacity: 0
            }, {
                duration: this.settings.animation_duration,
                step: function(now) {
                    var scale = 0.8 + (0.2 * now);
                    $(this).css('transform', 'scale(' + scale + ')');
                },
                complete: function() {
                    self.$lightbox.hide();
                    self.resetImageTransform();
                }
            });
            
            // Body scroll'u geri aç
            $('body').removeClass('pgal-lightbox-open');
            
            // Analytics
            this.trackEvent('lightbox_close', {
                viewed_images: this.currentIndex + 1,
                total_images: this.images.length
            });
        },
        
        loadCurrentImage: function() {
            if (!this.images[this.currentIndex]) {
                return;
            }
            
            var currentImage = this.images[this.currentIndex];
            var self = this;
            
            // Loading göster
            this.showLoading();
            
            // Resmi yükle
            var $newImage = $('<img>');
            $newImage.on('load', function() {
                self.$mainImage.attr('src', this.src);
                self.$mainImage.attr('alt', currentImage.title);
                self.hideLoading();
                self.resetImageTransform();
                self.updateImageInfo();
                
                // Preload next image
                if (self.settings.preload_next_image) {
                    self.preloadNextImage();
                }
            });
            
            $newImage.on('error', function() {
                self.onImageError();
            });
            
            $newImage.attr('src', currentImage.full_src || currentImage.src);
        },
        
        showLoading: function() {
            this.$loading.fadeIn(200);
        },
        
        hideLoading: function() {
            this.$loading.fadeOut(200);
        },
        
        onImageLoad: function() {
            this.hideLoading();
            this.updateImagePosition();
        },
        
        onImageError: function() {
            this.hideLoading();
            this.showError(this.strings.error);
        },
        
        showError: function(message) {
            var errorHtml = '<div class="pgal-error">' + message + '</div>';
            this.$imageWrapper.html(errorHtml);
        },
        
        updateInterface: function() {
            this.updateImageCounter();
            this.updateImageTitle();
            this.updateNavigationButtons();
            this.generateThumbnails();
        },
        
        updateImageCounter: function() {
            if (!this.settings.show_image_counter) {
                return;
            }
            
            this.$lightbox.find('.pgal-current-index').text(this.currentIndex + 1);
            this.$lightbox.find('.pgal-total-count').text(this.images.length);
        },
        
        updateImageTitle: function() {
            if (!this.settings.show_image_title) {
                return;
            }
            
            var currentImage = this.images[this.currentIndex];
            this.$lightbox.find('.pgal-image-title').text(currentImage.title || '');
        },
        
        updateNavigationButtons: function() {
            // Önceki butonu
            if (this.currentIndex === 0) {
                this.$lightbox.find('.pgal-nav-prev').addClass('pgal-disabled');
            } else {
                this.$lightbox.find('.pgal-nav-prev').removeClass('pgal-disabled');
            }
            
            // Sonraki butonu
            if (this.currentIndex === this.images.length - 1) {
                this.$lightbox.find('.pgal-nav-next').addClass('pgal-disabled');
            } else {
                this.$lightbox.find('.pgal-nav-next').removeClass('pgal-disabled');
            }
        },
        
        generateThumbnails: function() {
            if (!this.settings.enable_thumbnails) {
                return;
            }
            
            var $thumbnailsList = this.$lightbox.find('.pgal-thumbnails-list');
            $thumbnailsList.empty();
            
            var self = this;
            
            this.images.forEach(function(image, index) {
                var $thumb = $('<div class="pgal-thumbnail-item">');
                $thumb.data('index', index);
                
                if (index === self.currentIndex) {
                    $thumb.addClass('pgal-active');
                }
                
                var $thumbImg = $('<img>');
                $thumbImg.attr('src', image.src);
                $thumbImg.attr('alt', image.title);
                
                $thumb.append($thumbImg);
                $thumbnailsList.append($thumb);
            });
        },
        
        updateImageInfo: function() {
            var currentImage = this.images[this.currentIndex];
            var $infoPanel = this.$lightbox.find('.pgal-info-panel');
            
            // Temel bilgiler
            $infoPanel.find('.pgal-filename').text(this.getFilenameFromUrl(currentImage.full_src));
            
            // EXIF bilgilerini yükle
            if (this.settings.enable_exif && currentImage.attachment_id) {
                this.loadExifData(currentImage.attachment_id);
            }
        },
        
        getFilenameFromUrl: function(url) {
            return url.substring(url.lastIndexOf('/') + 1);
        },
        
        loadExifData: function(attachmentId) {
            var self = this;
            
            $.post(projectGalleryLightbox.ajaxurl, {
                action: 'get_image_exif',
                attachment_id: attachmentId,
                nonce: projectGalleryLightbox.nonce
            }, function(response) {
                if (response.success) {
                    self.displayExifData(response.data);
                }
            });
        },
        
        displayExifData: function(exifData) {
            var $exifSection = this.$lightbox.find('.pgal-exif-data');
            var $exifContent = $exifSection.find('.pgal-exif-content');
            
            $exifContent.empty();
            
            if (Object.keys(exifData).length === 0) {
                $exifContent.html('<p>EXIF bilgisi bulunamadı.</p>');
                return;
            }
            
            Object.keys(exifData).forEach(function(key) {
                var $item = $('<div class="pgal-info-item">');
                $item.append('<label>' + key + ':</label>');
                $item.append('<span>' + exifData[key] + '</span>');
                $exifContent.append($item);
            });
            
            $exifSection.show();
        },
        
        previousImage: function() {
            if (this.currentIndex > 0) {
                this.currentIndex--;
                this.loadCurrentImage();
                this.updateInterface();
                this.trackEvent('navigate_previous');
            }
        },
        
        nextImage: function() {
            if (this.currentIndex < this.images.length - 1) {
                this.currentIndex++;
                this.loadCurrentImage();
                this.updateInterface();
                this.trackEvent('navigate_next');
            }
        },
        
        goToImage: function(index) {
            if (index >= 0 && index < this.images.length && index !== this.currentIndex) {
                this.currentIndex = index;
                this.loadCurrentImage();
                this.updateInterface();
                this.trackEvent('navigate_direct', { target_index: index });
            }
        },
        
        preloadNextImage: function() {
            var nextIndex = this.currentIndex + 1;
            if (nextIndex < this.images.length) {
                var nextImage = this.images[nextIndex];
                var $preload = $('<img>');
                $preload.attr('src', nextImage.full_src || nextImage.src);
            }
        },
        
        // Zoom işlevleri
        zoomIn: function() {
            var newZoom = this.zoomLevel * 1.5;
            this.setZoom(Math.min(newZoom, 5));
        },
        
        zoomOut: function() {
            var newZoom = this.zoomLevel / 1.5;
            this.setZoom(Math.max(newZoom, 0.1));
        },
        
        zoomFit: function() {
            this.setZoom(1);
            this.translateX = 0;
            this.translateY = 0;
            this.updateImageTransform();
        },
        
        setZoom: function(level) {
            this.zoomLevel = level;
            this.isZoomed = level > 1;
            this.updateZoomIndicator();
            this.updateImageTransform();
            this.updateImagePosition();
        },
        
        updateZoomIndicator: function() {
            var percentage = Math.round(this.zoomLevel * 100);
            this.$lightbox.find('.pgal-zoom-level').text(percentage + '%');
        },
        
        // Döndürme işlevleri
        rotateLeft: function() {
            this.rotation -= 90;
            this.updateImageTransform();
            this.trackEvent('rotate', { direction: 'left' });
        },
        
        rotateRight: function() {
            this.rotation += 90;
            this.updateImageTransform();
            this.trackEvent('rotate', { direction: 'right' });
        },
        
        updateImageTransform: function() {
            var transform = 'translate(' + this.translateX + 'px, ' + this.translateY + 'px) ' +
                           'scale(' + this.zoomLevel + ') ' +
                           'rotate(' + this.rotation + 'deg)';
            
            this.$mainImage.css('transform', transform);
        },
        
        resetImageTransform: function() {
            this.zoomLevel = 1;
            this.rotation = 0;
            this.translateX = 0;
            this.translateY = 0;
            this.isZoomed = false;
            this.updateImageTransform();
            this.updateZoomIndicator();
        },
        
        updateImagePosition: function() {
            // Resmi ekran merkezine hizala
            if (this.settings.auto_fit) {
                this.centerImage();
            }
        },
        
        centerImage: function() {
            var $container = this.$imageWrapper;
            var containerWidth = $container.width();
            var containerHeight = $container.height();
            
            var imageWidth = this.$mainImage.width() * this.zoomLevel;
            var imageHeight = this.$mainImage.height() * this.zoomLevel;
            
            if (imageWidth < containerWidth) {
                this.translateX = 0;
            }
            
            if (imageHeight < containerHeight) {
                this.translateY = 0;
            }
            
            this.updateImageTransform();
        },
        
        // Tam ekran işlevleri
        toggleFullscreen: function() {
            if (!document.fullscreenElement) {
                this.enterFullscreen();
            } else {
                this.exitFullscreen();
            }
        },
        
        enterFullscreen: function() {
            var element = this.$lightbox[0];
            
            if (element.requestFullscreen) {
                element.requestFullscreen();
            } else if (element.mozRequestFullScreen) {
                element.mozRequestFullScreen();
            } else if (element.webkitRequestFullscreen) {
                element.webkitRequestFullscreen();
            } else if (element.msRequestFullscreen) {
                element.msRequestFullscreen();
            }
            
            this.trackEvent('fullscreen_enter');
        },
        
        exitFullscreen: function() {
            if (document.exitFullscreen) {
                document.exitFullscreen();
            } else if (document.mozCancelFullScreen) {
                document.mozCancelFullScreen();
            } else if (document.webkitExitFullscreen) {
                document.webkitExitFullscreen();
            } else if (document.msExitFullscreen) {
                document.msExitFullscreen();
            }
            
            this.trackEvent('fullscreen_exit');
        },
        
        // Slayt gösterisi işlevleri
        toggleSlideshow: function() {
            if (this.slideshowActive) {
                this.stopSlideshow();
            } else {
                this.startSlideshow();
            }
        },
        
        startSlideshow: function() {
            if (this.images.length <= 1) {
                return;
            }
            
            this.slideshowActive = true;
            this.updateSlideshowButton();
            
            var self = this;
            this.slideshowTimer = setInterval(function() {
                if (self.currentIndex < self.images.length - 1) {
                    self.nextImage();
                } else {
                    self.goToImage(0); // Başa dön
                }
            }, this.settings.slideshow_duration);
            
            this.trackEvent('slideshow_start');
        },
        
        stopSlideshow: function() {
            this.slideshowActive = false;
            this.updateSlideshowButton();
            
            if (this.slideshowTimer) {
                clearInterval(this.slideshowTimer);
                this.slideshowTimer = null;
            }
            
            this.trackEvent('slideshow_stop');
        },
        
        updateSlideshowButton: function() {
            var $btn = this.$lightbox.find('.pgal-btn-slideshow');
            var $icon = $btn.find('.pgal-icon-play');
            
            if (this.slideshowActive) {
                $icon.html('⏸');
                $btn.attr('title', this.strings.slideshow_pause);
            } else {
                $icon.html('▶');
                $btn.attr('title', this.strings.slideshow_play);
            }
        },
        
        // Panel işlevleri
        toggleInfoPanel: function() {
            var $panel = this.$lightbox.find('.pgal-info-panel');
            
            if ($panel.is(':visible')) {
                this.closeInfoPanel();
            } else {
                this.openInfoPanel();
            }
        },
        
        openInfoPanel: function() {
            this.closeOtherPanels(['info']);
            this.$lightbox.find('.pgal-info-panel').slideDown(this.settings.animation_duration);
            this.trackEvent('panel_open', { panel: 'info' });
        },
        
        closeInfoPanel: function() {
            this.$lightbox.find('.pgal-info-panel').slideUp(this.settings.animation_duration);
        },
        
        toggleSharePanel: function() {
            var $panel = this.$lightbox.find('.pgal-share-panel');
            
            if ($panel.is(':visible')) {
                this.closeSharePanel();
            } else {
                this.openSharePanel();
            }
        },
        
        openSharePanel: function() {
            this.closeOtherPanels(['share']);
            
            var currentImage = this.images[this.currentIndex];
            var shareUrl = window.location.href;
            
            this.$lightbox.find('.pgal-share-url-input').val(shareUrl);
            this.$lightbox.find('.pgal-share-panel').slideDown(this.settings.animation_duration);
            
            this.trackEvent('panel_open', { panel: 'share' });
        },
        
        closeSharePanel: function() {
            this.$lightbox.find('.pgal-share-panel').slideUp(this.settings.animation_duration);
        },
        
        toggleHelpPanel: function() {
            var $panel = this.$lightbox.find('.pgal-help-panel');
            
            if ($panel.is(':visible')) {
                this.closeHelpPanel();
            } else {
                this.openHelpPanel();
            }
        },
        
        openHelpPanel: function() {
            this.closeOtherPanels(['help']);
            this.$lightbox.find('.pgal-help-panel').slideDown(this.settings.animation_duration);
            this.trackEvent('panel_open', { panel: 'help' });
        },
        
        closeHelpPanel: function() {
            this.$lightbox.find('.pgal-help-panel').slideUp(this.settings.animation_duration);
        },
        
        closeOtherPanels: function(except = []) {
            var panels = ['info', 'share', 'help'];
            var self = this;
            
            panels.forEach(function(panel) {
                if (except.indexOf(panel) === -1) {
                    self.$lightbox.find('.pgal-' + panel + '-panel').slideUp(200);
                }
            });
        },
        
        closeAllPanels: function() {
            this.closeOtherPanels();
        },
        
        toggleThumbnails: function() {
            var $container = this.$lightbox.find('.pgal-thumbnails-container');
            
            if ($container.is(':visible')) {
                $container.slideUp(this.settings.animation_duration);
            } else {
                $container.slideDown(this.settings.animation_duration);
            }
        },
        
        // Sosyal medya paylaşımı
        shareImage: function(platform) {
            var currentImage = this.images[this.currentIndex];
            var shareUrls = projectGalleryLightbox.share_urls;
            
            if (!shareUrls[platform]) {
                return;
            }
            
            var shareUrl = shareUrls[platform]
                .replace('{url}', encodeURIComponent(window.location.href))
                .replace('{title}', encodeURIComponent(currentImage.title))
                .replace('{image}', encodeURIComponent(currentImage.full_src));
            
            // Yeni pencerede aç
            window.open(shareUrl, 'share_' + platform, 'width=600,height=400,scrollbars=yes,resizable=yes');
            
            // Analytics ve sunucu tarafı tracking
            this.trackShare(platform, currentImage.attachment_id);
        },
        
        trackShare: function(platform, attachmentId) {
            $.post(projectGalleryLightbox.ajaxurl, {
                action: 'share_image',
                platform: platform,
                attachment_id: attachmentId,
                nonce: projectGalleryLightbox.nonce
            });
            
            this.trackEvent('social_share', {
                platform: platform,
                attachment_id: attachmentId
            });
        },
        
        copyShareUrl: function() {
            var $input = this.$lightbox.find('.pgal-share-url-input');
            $input.select();
            
            try {
                document.execCommand('copy');
                this.showNotification(this.strings.share_copied, 'success');
            } catch (err) {
                this.showNotification('Kopyalama başarısız', 'error');
            }
            
            this.trackEvent('share_url_copy');
        },
        
        // İndirme işlevi
        downloadImage: function() {
            var currentImage = this.images[this.currentIndex];
            var self = this;
            
            if (!currentImage.attachment_id) {
                this.showNotification('İndirme mevcut değil', 'error');
                return;
            }
            
            $.post(projectGalleryLightbox.ajaxurl, {
                action: 'download_image',
                attachment_id: currentImage.attachment_id,
                nonce: projectGalleryLightbox.nonce
            }, function(response) {
                if (response.success) {
                    // İndirmeyi başlat
                    var link = document.createElement('a');
                    link.href = response.data.download_url;
                    link.download = response.data.filename;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    
                    self.trackEvent('image_download', {
                        attachment_id: currentImage.attachment_id,
                        filename: response.data.filename
                    });
                } else {
                    self.showNotification(response.data, 'error');
                }
            });
        },
        
        // Karşılaştırma modu
        toggleComparisonMode: function() {
            // Bu özellik daha karmaşık olduğu için şimdilik placeholder
            this.showNotification('Karşılaştırma modu yakında eklenecek', 'info');
        },
        
        // Touch gesture desteği
        setupTouchGestures: function() {
            if (!this.settings.mobile_gestures || !window.Hammer) {
                return;
            }
            
            var self = this;
            this.hammer = new Hammer(this.$imageWrapper[0]);
            
            // Pan (sürükleme) desteği
            this.hammer.get('pan').set({ direction: Hammer.DIRECTION_ALL });
            
            // Pinch (çimdikleme) desteği
            this.hammer.get('pinch').set({ enable: true });
            
            // Swipe desteği
            this.hammer.get('swipe').set({ direction: Hammer.DIRECTION_HORIZONTAL });
            
            // Event handlers
            this.hammer.on('panstart', function(e) {
                self.onPanStart(e);
            });
            
            this.hammer.on('panmove', function(e) {
                self.onPanMove(e);
            });
            
            this.hammer.on('panend', function(e) {
                self.onPanEnd(e);
            });
            
            this.hammer.on('pinchstart', function(e) {
                self.onPinchStart(e);
            });
            
            this.hammer.on('pinchmove', function(e) {
                self.onPinchMove(e);
            });
            
            this.hammer.on('swipeleft', function(e) {
                self.nextImage();
            });
            
            this.hammer.on('swiperight', function(e) {
                self.previousImage();
            });
            
            this.hammer.on('doubletap', function(e) {
                if (self.zoomLevel > 1) {
                    self.zoomFit();
                } else {
                    self.setZoom(2);
                }
            });
        },
        
        onPanStart: function(e) {
            this.isDragging = true;
            this.lastPanData = {
                x: this.translateX,
                y: this.translateY
            };
        },
        
        onPanMove: function(e) {
            if (!this.isDragging || !this.isZoomed) {
                return;
            }
            
            this.translateX = this.lastPanData.x + e.deltaX;
            this.translateY = this.lastPanData.y + e.deltaY;
            this.updateImageTransform();
        },
        
        onPanEnd: function(e) {
            this.isDragging = false;
        },
        
        onPinchStart: function(e) {
            this.lastPinchScale = this.zoomLevel;
        },
        
        onPinchMove: function(e) {
            var newZoom = this.lastPinchScale * e.scale;
            this.setZoom(Math.max(0.1, Math.min(5, newZoom)));
        },
        
        // Yardımcı işlevler
        showNotification: function(message, type) {
            var $notification = $('<div class="pgal-notification pgal-notification-' + type + '">');
            $notification.text(message);
            
            $('body').append($notification);
            
            setTimeout(function() {
                $notification.addClass('pgal-show');
            }, 100);
            
            setTimeout(function() {
                $notification.removeClass('pgal-show');
                setTimeout(function() {
                    $notification.remove();
                }, 300);
            }, 3000);
        },
        
        trackEvent: function(eventName, data) {
            // Google Analytics veya başka analytics servisi için
            if (typeof gtag !== 'undefined') {
                gtag('event', eventName, {
                    event_category: 'project_gallery_lightbox',
                    custom_parameters: data
                });
            }
            
            // Console'da debug için
            if (window.console && window.console.log) {
                console.log('ProjectGallery Lightbox Event:', eventName, data);
            }
        }
    };
    
    // Sayfa yüklendiğinde başlat
    $(document).ready(function() {
        ProjectGalleryLightbox.init();
    });
    
    // Global erişim için
    window.ProjectGalleryLightbox = ProjectGalleryLightbox;
    
})(jQuery);