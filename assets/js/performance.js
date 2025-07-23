/**
 * Project Gallery Performance Optimization JavaScript
 * Advanced performance features and optimizations
 */

(function($) {
    'use strict';
    
    // Configuration from WordPress
    const config = projectGalleryPerf || {};
    
    // Performance monitoring
    class PerformanceMonitor {
        constructor() {
            this.metrics = {};
            this.startTime = performance.now();
            this.observers = {};
            
            this.initPerformanceObservers();
            this.initLazyLoading();
            this.initProgressiveLoading();
        }
        
        initPerformanceObservers() {
            // Intersection Observer for lazy loading
            if ('IntersectionObserver' in window) {
                this.observers.lazy = new IntersectionObserver(
                    this.handleLazyLoading.bind(this),
                    {
                        rootMargin: '50px 0px',
                        threshold: config.preload_threshold || 0.1
                    }
                );
            }
            
            // Performance Observer for monitoring
            if ('PerformanceObserver' in window) {
                try {
                    const perfObserver = new PerformanceObserver((list) => {
                        for (const entry of list.getEntries()) {
                            this.recordMetric(entry.name, entry.duration);
                        }
                    });
                    
                    perfObserver.observe({ entryTypes: ['measure', 'navigation'] });
                } catch (e) {
                    console.warn('Performance Observer not supported');
                }
            }
        }
        
        initLazyLoading() {
            if (!config.lazy_loading) return;
            
            const lazyImages = document.querySelectorAll('img[data-src]');
            
            lazyImages.forEach(img => {
                if (this.observers.lazy) {
                    this.observers.lazy.observe(img);
                } else {
                    // Fallback for browsers without Intersection Observer
                    this.loadImageFallback(img);
                }
            });
        }
        
        handleLazyLoading(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    this.loadImage(img);
                    this.observers.lazy.unobserve(img);
                }
            });
        }
        
        loadImage(img) {
            const startTime = performance.now();
            
            img.classList.add('loading');
            
            const tempImg = new Image();
            tempImg.onload = () => {
                img.src = img.dataset.src;
                img.classList.remove('loading');
                img.classList.add('loaded');
                
                const loadTime = performance.now() - startTime;
                this.recordMetric('image_load_time', loadTime);
                
                // Trigger custom event
                img.dispatchEvent(new CustomEvent('imageLoaded', {
                    detail: { loadTime, src: img.src }
                }));
            };
            
            tempImg.onerror = () => {
                img.classList.remove('loading');
                img.classList.add('error');
                this.handleImageError(img);
            };
            
            tempImg.src = img.dataset.src;
        }
        
        loadImageFallback(img) {
            // Simple fallback for older browsers
            setTimeout(() => {
                if (this.isElementInViewport(img)) {
                    this.loadImage(img);
                }
            }, 100);
        }
        
        handleImageError(img) {
            // Try WebP fallback
            if (img.dataset.src.includes('.webp')) {
                const fallbackSrc = img.dataset.src.replace('.webp', '.jpg');
                img.dataset.src = fallbackSrc;
                this.loadImage(img);
            } else {
                // Show placeholder or error state
                img.alt = 'Image failed to load';
                console.warn('Failed to load image:', img.dataset.src);
            }
        }
        
        initProgressiveLoading() {
            if (!config.progressive_loading) return;
            
            this.initInfiniteScroll();
            this.initImagePreloading();
        }
        
        initInfiniteScroll() {
            const loadMoreBtn = document.querySelector('.load-more-projects');
            if (!loadMoreBtn) return;
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        this.loadMoreProjects();
                    }
                });
            });
            
            observer.observe(loadMoreBtn);
        }
        
        loadMoreProjects() {
            const container = document.querySelector('.project-gallery');
            const currentPage = parseInt(container.dataset.page || 1);
            
            $.ajax({
                url: config.ajax_url,
                type: 'POST',
                data: {
                    action: 'load_more_projects',
                    page: currentPage + 1,
                    posts_per_page: config.batch_size || 6,
                    nonce: config.nonce
                },
                beforeSend: () => {
                    $('.load-more-btn').text('Loading...').prop('disabled', true);
                },
                success: (response) => {
                    if (response.success) {
                        this.appendProjects(response.data.projects);
                        container.dataset.page = currentPage + 1;
                        
                        if (!response.data.has_more) {
                            $('.load-more-btn').hide();
                        }
                    }
                },
                complete: () => {
                    $('.load-more-btn').text('Load More').prop('disabled', false);
                }
            });
        }
        
        appendProjects(projects) {
            const container = document.querySelector('.project-gallery');
            
            projects.forEach(project => {
                const projectElement = this.createProjectElement(project);
                container.appendChild(projectElement);
                
                // Initialize lazy loading for new images
                const images = projectElement.querySelectorAll('img[data-src]');
                images.forEach(img => {
                    if (this.observers.lazy) {
                        this.observers.lazy.observe(img);
                    }
                });
            });
        }
        
        createProjectElement(project) {
            const element = document.createElement('div');
            element.className = 'project-item';
            element.innerHTML = `
                <a href="${project.permalink}" class="project-link">
                    <div class="project-thumbnail">
                        <img data-src="${project.featured_image}" 
                             src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='300' height='200'%3E%3Crect width='100%25' height='100%25' fill='%23f0f0f0'/%3E%3C/svg%3E"
                             alt="${project.title}"
                             class="lazy-load">
                        <div class="project-overlay">
                            <h3 class="project-title">${project.title}</h3>
                            <div class="project-categories">
                                ${project.categories.map(cat => `<span class="project-category">${cat.name}</span>`).join('')}
                            </div>
                        </div>
                    </div>
                </a>
            `;
            
            return element;
        }
        
        initImagePreloading() {
            const criticalImages = document.querySelectorAll('[data-critical="true"]');
            
            criticalImages.forEach(img => {
                this.preloadImage(img.src || img.dataset.src);
            });
        }
        
        preloadImage(src) {
            const link = document.createElement('link');
            link.rel = 'preload';
            link.as = 'image';
            link.href = src;
            document.head.appendChild(link);
        }
        
        recordMetric(name, value) {
            if (!this.metrics[name]) {
                this.metrics[name] = [];
            }
            
            this.metrics[name].push({
                value,
                timestamp: performance.now()
            });
            
            // Send to analytics if available
            if (typeof gtag !== 'undefined') {
                gtag('event', 'timing_complete', {
                    name: name,
                    value: Math.round(value)
                });
            }
        }
        
        getMetrics() {
            return this.metrics;
        }
        
        isElementInViewport(el) {
            const rect = el.getBoundingClientRect();
            return (
                rect.top >= 0 &&
                rect.left >= 0 &&
                rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
                rect.right <= (window.innerWidth || document.documentElement.clientWidth)
            );
        }
    }
    
    // Connection-aware loading
    class ConnectionAwareLoading {
        constructor() {
            this.connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
            this.init();
        }
        
        init() {
            if (this.connection) {
                this.adjustForConnection();
                this.connection.addEventListener('change', this.adjustForConnection.bind(this));
            }
        }
        
        adjustForConnection() {
            const effectiveType = this.connection.effectiveType;
            const saveData = this.connection.saveData;
            
            if (saveData || effectiveType === 'slow-2g' || effectiveType === '2g') {
                this.enableLowDataMode();
            } else if (effectiveType === '4g') {
                this.enableHighQualityMode();
            }
        }
        
        enableLowDataMode() {
            document.documentElement.classList.add('low-data-mode');
            
            // Reduce image quality
            const images = document.querySelectorAll('img[data-src]');
            images.forEach(img => {
                const src = img.dataset.src;
                if (src.includes('large')) {
                    img.dataset.src = src.replace('large', 'medium');
                }
            });
        }
        
        enableHighQualityMode() {
            document.documentElement.classList.remove('low-data-mode');
            
            // Preload more images
            this.preloadNextImages();
        }
        
        preloadNextImages() {
            const visibleImages = document.querySelectorAll('img.loaded');
            const nextImages = Array.from(document.querySelectorAll('img[data-src]')).slice(0, 3);
            
            nextImages.forEach(img => {
                const link = document.createElement('link');
                link.rel = 'preload';
                link.as = 'image';
                link.href = img.dataset.src;
                document.head.appendChild(link);
            });
        }
    }
    
    // Image optimization
    class ImageOptimizer {
        constructor() {
            this.webpSupport = this.checkWebPSupport();
            this.init();
        }
        
        checkWebPSupport() {
            return new Promise((resolve) => {
                const webP = new Image();
                webP.onload = webP.onerror = () => {
                    resolve(webP.height === 2);
                };
                webP.src = 'data:image/webp;base64,UklGRjoAAABXRUJQVlA4IC4AAACyAgCdASoCAAIALmk0mk0iIiIiIgBoSygABc6WWgAA/veff/0PP8bA//LwYAAA';
            });
        }
        
        async init() {
            const supportsWebP = await this.webpSupport;
            
            if (supportsWebP) {
                document.documentElement.classList.add('webp-support');
                this.convertToWebP();
            }
        }
        
        convertToWebP() {
            const images = document.querySelectorAll('img[data-src]');
            
            images.forEach(img => {
                const src = img.dataset.src;
                if (src && !src.includes('.webp')) {
                    const webpSrc = src.replace(/\.(jpg|jpeg|png)$/, '.webp');
                    
                    // Check if WebP version exists
                    this.checkImageExists(webpSrc).then(exists => {
                        if (exists) {
                            img.dataset.src = webpSrc;
                        }
                    });
                }
            });
        }
        
        checkImageExists(src) {
            return new Promise((resolve) => {
                const img = new Image();
                img.onload = () => resolve(true);
                img.onerror = () => resolve(false);
                img.src = src;
            });
        }
    }
    
    // Resource hints
    class ResourceHints {
        constructor() {
            this.addResourceHints();
        }
        
        addResourceHints() {
            // DNS prefetch for external resources
            this.addDNSPrefetch([
                '//fonts.googleapis.com',
                '//fonts.gstatic.com',
                '//www.youtube.com',
                '//player.vimeo.com'
            ]);
            
            // Preload critical resources
            this.preloadCriticalResources();
        }
        
        addDNSPrefetch(domains) {
            domains.forEach(domain => {
                const link = document.createElement('link');
                link.rel = 'dns-prefetch';
                link.href = domain;
                document.head.appendChild(link);
            });
        }
        
        preloadCriticalResources() {
            // Preload first few images
            const firstImages = document.querySelectorAll('img[data-src]');
            Array.from(firstImages).slice(0, 3).forEach(img => {
                const link = document.createElement('link');
                link.rel = 'preload';
                link.as = 'image';
                link.href = img.dataset.src;
                document.head.appendChild(link);
            });
        }
    }
    
    // Initialize all performance features
    $(document).ready(() => {
        const perfMonitor = new PerformanceMonitor();
        const connectionAware = new ConnectionAwareLoading();
        const imageOptimizer = new ImageOptimizer();
        const resourceHints = new ResourceHints();
        
        // Expose performance metrics for debugging
        window.projectGalleryPerformance = {
            getMetrics: () => perfMonitor.getMetrics(),
            monitor: perfMonitor,
            connectionAware,
            imageOptimizer,
            resourceHints
        };
        
        console.log('Project Gallery Performance: Initialized');
    });
    
})(jQuery);