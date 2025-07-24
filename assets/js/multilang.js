/**
 * Project Gallery Multi-language JavaScript
 * Çoklu dil desteği için frontend işlevleri
 */

(function($) {
    'use strict';
    
    var ProjectGalleryMultiLang = {
        
        currentLang: '',
        languages: {},
        ajaxurl: '',
        
        init: function() {
            if (typeof projectGalleryMultiLang !== 'undefined') {
                this.currentLang = projectGalleryMultiLang.current_lang;
                this.languages = projectGalleryMultiLang.languages;
                this.ajaxurl = projectGalleryMultiLang.ajaxurl;
                
                this.bindEvents();
                this.addLanguageIndicators();
            }
        },
        
        bindEvents: function() {
            // Dil değiştirme
            $(document).on('change', '#project-gallery-language', this.switchLanguage.bind(this));
            
            // AJAX dil değiştirme için shortcode güncelleme
            $(document).on('click', '.project-lang-switch', this.handleLangSwitch.bind(this));
            
            // URL parameter'larını dinle
            this.handleUrlLangParameter();
        },
        
        switchLanguage: function(newLang) {
            if (typeof newLang === 'object') {
                newLang = $(newLang.target).val();
            }
            
            if (newLang === this.currentLang) {
                return;
            }
            
            // Loading göster
            this.showLoading();
            
            // URL'yi güncelle
            this.updateUrl(newLang);
            
            // Galeriyi yeniden yükle
            this.reloadGallery(newLang);
            
            // Dil göstergelerini güncelle
            this.updateLanguageIndicators(newLang);
            
            this.currentLang = newLang;
        },
        
        handleLangSwitch: function(e) {
            e.preventDefault();
            var lang = $(e.target).data('lang');
            this.switchLanguage(lang);
        },
        
        handleUrlLangParameter: function() {
            var urlParams = new URLSearchParams(window.location.search);
            var langParam = urlParams.get('lang');
            
            if (langParam && langParam !== this.currentLang) {
                this.switchLanguage(langParam);
            }
        },
        
        updateUrl: function(lang) {
            var url = new URL(window.location);
            url.searchParams.set('lang', lang);
            
            // History API ile URL'yi güncelle (sayfa yenilemeden)
            if (history.pushState) {
                history.pushState({lang: lang}, '', url.toString());
            }
        },
        
        reloadGallery: function(lang) {
            var self = this;
            
            $('.project-gallery').each(function() {
                var $gallery = $(this);
                var shortcodeAtts = self.extractShortcodeAtts($gallery);
                
                // Dil parametresini ekle
                shortcodeAtts.lang = lang;
                
                // AJAX ile galeriyi yeniden yükle
                $.post(self.ajaxurl, {
                    action: 'reload_gallery_for_language',
                    lang: lang,
                    atts: shortcodeAtts,
                    nonce: projectGalleryMultiLang.nonce
                }, function(response) {
                    if (response.success) {
                        $gallery.html(response.data.html);
                        
                        // Animasyonları yeniden başlat
                        if (typeof ProjectGalleryJS !== 'undefined') {
                            ProjectGalleryJS.reinitialize();
                        }
                        
                        // Loading'i gizle
                        self.hideLoading();
                        
                        // Başarı mesajı
                        self.showNotification('Dil değiştirildi: ' + self.languages[lang].native_name, 'success');
                    } else {
                        self.hideLoading();
                        self.showNotification('Dil değiştirilemedi: ' + response.data, 'error');
                    }
                }).fail(function() {
                    self.hideLoading();
                    self.showNotification('Bağlantı hatası', 'error');
                });
            });
        },
        
        extractShortcodeAtts: function($gallery) {
            var atts = {};
            
            // Data attribute'larından shortcode parametrelerini çıkar
            if ($gallery.data('columns')) atts.columns = $gallery.data('columns');
            if ($gallery.data('kategori')) atts.kategori = $gallery.data('kategori');
            if ($gallery.data('limit')) atts.limit = $gallery.data('limit');
            
            return atts;
        },
        
        addLanguageIndicators: function() {
            // Her proje için dil göstergesi ekle
            $('.project-item').each(function() {
                var $item = $(this);
                var projectId = $item.data('project-id');
                
                if (projectId) {
                    // Mevcut dil göstergesi
                    var $indicator = $('<div class="project-lang-indicator">');
                    $indicator.html(this.getLanguageFlag(this.currentLang));
                    $item.append($indicator);
                }
            }.bind(this));
        },
        
        updateLanguageIndicators: function(newLang) {
            $('.project-lang-indicator').each(function() {
                $(this).html(this.getLanguageFlag(newLang));
            }.bind(this));
        },
        
        getLanguageFlag: function(langCode) {
            var flags = {
                'tr': '🇹🇷',
                'en': '🇺🇸',
                'de': '🇩🇪',
                'fr': '🇫🇷',
                'es': '🇪🇸',
                'it': '🇮🇹',
                'ru': '🇷🇺',
                'ar': '🇸🇦',
                'zh': '🇨🇳',
                'ja': '🇯🇵'
            };
            
            return flags[langCode] || '🌐';
        },
        
        showLoading: function() {
            if ($('.project-gallery-loading').length === 0) {
                var $loading = $('<div class="project-gallery-loading">');
                $loading.html('<div class="loading-spinner"></div><p>Dil değiştiriliyor...</p>');
                $('body').append($loading);
            }
            $('.project-gallery-loading').fadeIn();
        },
        
        hideLoading: function() {
            $('.project-gallery-loading').fadeOut();
        },
        
        showNotification: function(message, type) {
            var $notification = $('<div class="project-gallery-notification">');
            $notification.addClass('notification-' + type);
            $notification.html('<span>' + message + '</span><button class="notification-close">×</button>');
            
            $('body').append($notification);
            
            setTimeout(function() {
                $notification.addClass('show');
            }, 100);
            
            // Otomatik kapat
            setTimeout(function() {
                $notification.removeClass('show');
                setTimeout(function() {
                    $notification.remove();
                }, 300);
            }, 3000);
            
            // Manuel kapat
            $notification.find('.notification-close').on('click', function() {
                $notification.removeClass('show');
                setTimeout(function() {
                    $notification.remove();
                }, 300);
            });
        },
        
        // Language Statistics
        getLanguageStats: function() {
            var self = this;
            
            return $.post(this.ajaxurl, {
                action: 'get_language_stats',
                nonce: projectGalleryMultiLang.nonce
            }).done(function(response) {
                if (response.success) {
                    self.displayLanguageStats(response.data);
                }
            });
        },
        
        displayLanguageStats: function(stats) {
            console.log('Language Statistics:', stats);
            
            // Admin panelde istatistikleri göster
            if ($('.language-stats').length > 0) {
                this.updateAdminStats(stats);
            }
        },
        
        updateAdminStats: function(stats) {
            Object.keys(stats).forEach(function(lang) {
                var $statCard = $('.stat-card[data-lang="' + lang + '"]');
                if ($statCard.length > 0) {
                    $statCard.find('.stat-number').text(stats[lang].count);
                    $statCard.find('.stat-percentage').text(stats[lang].percentage + '%');
                }
            });
        },
        
        // Fallback Language Support
        handleMissingTranslation: function(projectId, lang) {
            console.log('Missing translation for project ' + projectId + ' in language ' + lang);
            
            // Fallback olarak varsayılan dili kullan
            if (lang !== this.getDefaultLanguage()) {
                return this.loadProjectInLanguage(projectId, this.getDefaultLanguage());
            }
            
            return null;
        },
        
        getDefaultLanguage: function() {
            // En çok içeriğe sahip dili varsayılan olarak döndür
            return Object.keys(this.languages)[0] || 'tr';
        },
        
        loadProjectInLanguage: function(projectId, lang) {
            return $.post(this.ajaxurl, {
                action: 'get_project_translation',
                project_id: projectId,
                lang: lang,
                nonce: projectGalleryMultiLang.nonce
            });
        },
        
        // Browser Language Detection
        detectBrowserLanguage: function() {
            var browserLang = navigator.language || navigator.userLanguage;
            var langCode = browserLang.split('-')[0]; // 'en-US' -> 'en'
            
            if (this.languages[langCode]) {
                return langCode;
            }
            
            return this.getDefaultLanguage();
        },
        
        // Auto-switch based on browser language
        autoSwitchLanguage: function() {
            var preferredLang = this.detectBrowserLanguage();
            
            if (preferredLang !== this.currentLang) {
                this.switchLanguage(preferredLang);
            }
        },
        
        // Cookie Support for Language Preference
        saveLanguagePreference: function(lang) {
            if (typeof Cookies !== 'undefined') {
                Cookies.set('project_gallery_lang', lang, { expires: 365 });
            } else {
                // Fallback to localStorage
                localStorage.setItem('project_gallery_lang', lang);
            }
        },
        
        getLanguagePreference: function() {
            if (typeof Cookies !== 'undefined') {
                return Cookies.get('project_gallery_lang');
            } else {
                return localStorage.getItem('project_gallery_lang');
            }
        },
        
        // RTL Language Support
        handleRTLLanguages: function(lang) {
            var rtlLanguages = ['ar', 'he', 'fa', 'ur'];
            
            if (rtlLanguages.includes(lang)) {
                $('body').addClass('rtl-language');
                $('.project-gallery').attr('dir', 'rtl');
            } else {
                $('body').removeClass('rtl-language');
                $('.project-gallery').attr('dir', 'ltr');
            }
        }
    };
    
    // Global erişim için
    window.projectGalleryMultiLang = ProjectGalleryMultiLang;
    
    // Sayfa yüklendiğinde başlat
    $(document).ready(function() {
        ProjectGalleryMultiLang.init();
        
        // Dil tercihlerini kontrol et
        var savedLang = ProjectGalleryMultiLang.getLanguagePreference();
        if (savedLang && savedLang !== ProjectGalleryMultiLang.currentLang) {
            ProjectGalleryMultiLang.switchLanguage(savedLang);
        }
    });
    
    // CSS Styles for Multi-language Features
    var styles = `
        <style>
        .project-gallery-loading {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.8);
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            color: white;
        }
        
        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-top: 3px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .project-gallery-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            transform: translateX(100%);
            transition: transform 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .project-gallery-notification.show {
            transform: translateX(0);
        }
        
        .project-gallery-notification.notification-success {
            border-left: 4px solid #46b450;
        }
        
        .project-gallery-notification.notification-error {
            border-left: 4px solid #dc3232;
        }
        
        .notification-close {
            background: none;
            border: none;
            font-size: 18px;
            cursor: pointer;
            color: #666;
            padding: 0;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .notification-close:hover {
            color: #333;
        }
        
        .project-lang-indicator {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            z-index: 10;
        }
        
        .project-gallery-lang-selector {
            margin-bottom: 20px;
            text-align: right;
        }
        
        .project-gallery-lang-selector select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: white;
            font-size: 14px;
            cursor: pointer;
        }
        
        .project-gallery-lang-selector select:hover {
            border-color: #007cba;
        }
        
        .project-gallery-lang-selector select:focus {
            outline: none;
            border-color: #007cba;
            box-shadow: 0 0 0 2px rgba(0, 123, 186, 0.2);
        }
        
        /* RTL Language Support */
        .rtl-language .project-gallery {
            direction: rtl;
        }
        
        .rtl-language .project-lang-indicator {
            right: auto;
            left: 10px;
        }
        
        .rtl-language .project-gallery-lang-selector {
            text-align: left;
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .project-gallery-notification {
                right: 10px;
                left: 10px;
                transform: translateY(-100%);
            }
            
            .project-gallery-notification.show {
                transform: translateY(0);
            }
            
            .project-gallery-lang-selector {
                text-align: center;
                margin-bottom: 15px;
            }
            
            .project-gallery-lang-selector select {
                width: 100%;
                max-width: 200px;
            }
        }
        </style>
    `;
    
    $('head').append(styles);
    
})(jQuery);