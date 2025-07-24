/**
 * Project Gallery Social Sharing JavaScript
 * Advanced social media integration and sharing features
 */

(function($) {
    'use strict';
    
    // Configuration from WordPress
    const config = projectGallerySocial || {};
    
    class SocialShareManager {
        constructor() {
            this.platforms = {
                facebook: {
                    name: 'Facebook',
                    url: 'https://www.facebook.com/sharer/sharer.php?u={url}',
                    popup: { width: 600, height: 400 }
                },
                twitter: {
                    name: 'Twitter',
                    url: 'https://twitter.com/intent/tweet?url={url}&text={title}',
                    popup: { width: 600, height: 300 }
                },
                linkedin: {
                    name: 'LinkedIn',
                    url: 'https://www.linkedin.com/sharing/share-offsite/?url={url}',
                    popup: { width: 600, height: 400 }
                },
                pinterest: {
                    name: 'Pinterest',
                    url: 'https://pinterest.com/pin/create/button/?url={url}&media={image}&description={title}',
                    popup: { width: 750, height: 350 }
                },
                whatsapp: {
                    name: 'WhatsApp',
                    url: 'https://wa.me/?text={title} {url}',
                    popup: { width: 600, height: 400 }
                },
                telegram: {
                    name: 'Telegram',
                    url: 'https://t.me/share/url?url={url}&text={title}',
                    popup: { width: 600, height: 400 }
                },
                email: {
                    name: 'Email',
                    url: 'mailto:?subject={title}&body={url}',
                    popup: false
                }
            };
            
            this.init();
        }
        
        init() {
            this.bindEvents();
            this.loadShareCounts();
            this.initQRCodeGenerator();
        }
        
        bindEvents() {
            // Share button clicks
            $(document).on('click', '.social-share-trigger', this.handleShareClick.bind(this));
            $(document).on('click', '.social-share-btn', this.handlePlatformShare.bind(this));
            
            // Modal controls
            $(document).on('click', '#open-share-modal, .share-project-btn', this.openShareModal.bind(this));
            $(document).on('click', '.social-share-close', this.closeShareModal.bind(this));
            $(document).on('click', '.qr-modal-close', this.closeQRModal.bind(this));
            
            // Custom features
            $(document).on('click', '#generate-share-image', this.generateShareImage.bind(this));
            $(document).on('click', '#create-story', this.createStoryTemplate.bind(this));
            $(document).on('click', '#qr-code-generate', this.generateQRCode.bind(this));
            $(document).on('click', '#download-qr', this.downloadQRCode.bind(this));
            
            // Close modal on outside click
            $(document).on('click', '.social-share-modal', (e) => {
                if (e.target === e.currentTarget) {
                    this.closeShareModal();
                }
            });
            
            // Keyboard shortcuts
            $(document).on('keydown', this.handleKeyboardShortcuts.bind(this));
        }
        
        handleShareClick(e) {
            e.preventDefault();
            
            const button = $(e.currentTarget);
            const platform = button.data('platform');
            const projectId = button.data('project-id');
            
            if (platform === 'more') {
                this.openShareModal();
            } else {
                this.shareToP1atform(platform, {
                    title: config.project_title,
                    url: config.project_url,
                    image: config.project_image
                });
            }
        }
        
        handlePlatformShare(e) {
            e.preventDefault();
            
            const button = $(e.currentTarget);
            const platform = button.data('platform');
            
            if (platform === 'copy') {
                this.copyToClipboard();
            } else {
                this.shareToP1atform(platform, {
                    title: config.project_title,
                    url: config.project_url,
                    image: config.project_image
                });
            }
        }
        
        shareToP1atform(platform, data) {
            if (!this.platforms[platform]) {
                console.error('Unknown platform:', platform);
                return;
            }
            
            const platformConfig = this.platforms[platform];
            let shareUrl = platformConfig.url;
            
            // Replace placeholders
            shareUrl = shareUrl.replace('{url}', encodeURIComponent(data.url));
            shareUrl = shareUrl.replace('{title}', encodeURIComponent(data.title));
            shareUrl = shareUrl.replace('{image}', encodeURIComponent(data.image));
            
            if (platformConfig.popup) {
                this.openPopup(shareUrl, platformConfig.popup);
            } else {
                window.location.href = shareUrl;
            }
            
            // Track the share
            this.trackShare(platform);
        }
        
        openPopup(url, options) {
            const left = (screen.width - options.width) / 2;
            const top = (screen.height - options.height) / 2;
            
            const popup = window.open(
                url,
                'share',
                `width=${options.width},height=${options.height},left=${left},top=${top},resizable=yes,scrollbars=yes`
            );
            
            // Focus the popup
            if (popup) {
                popup.focus();
            }
        }
        
        copyToClipboard() {
            const url = config.project_url;
            
            if (navigator.clipboard) {
                navigator.clipboard.writeText(url).then(() => {
                    this.showMessage(config.strings.copy_success, 'success');
                }).catch(() => {
                    this.fallbackCopyToClipboard(url);
                });
            } else {
                this.fallbackCopyToClipboard(url);
            }
            
            this.trackShare('copy');
        }
        
        fallbackCopyToClipboard(text) {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            textArea.style.top = '-999999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            
            try {
                document.execCommand('copy');
                this.showMessage(config.strings.copy_success, 'success');
            } catch (err) {
                this.showMessage('Failed to copy link', 'error');
            }
            
            document.body.removeChild(textArea);
        }
        
        trackShare(platform) {
            $.ajax({
                url: config.ajax_url,
                type: 'POST',
                data: {
                    action: 'share_project',
                    project_id: config.project_id,
                    platform: platform,
                    nonce: config.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.updateShareCounts(response.data.shares);
                        this.showMessage(config.strings.share_success, 'success');
                    }
                },
                error: () => {
                    this.showMessage(config.strings.share_error, 'error');
                }
            });
        }
        
        openShareModal() {
            $('#social-share-modal').fadeIn(300);
            this.loadShareStats();
        }
        
        closeShareModal() {
            $('#social-share-modal').fadeOut(300);
        }
        
        closeQRModal() {
            $('#qr-code-modal').fadeOut(300);
        }
        
        loadShareCounts() {
            const counters = $('.share-count');
            
            counters.each((index, counter) => {
                const platform = $(counter).data('platform');
                // This would load from server/API
                $(counter).text('0');
            });
        }
        
        updateShareCounts(shares) {
            Object.keys(shares).forEach(platform => {
                $(`.share-count[data-platform="${platform}"]`).text(shares[platform]);
            });
        }
        
        loadShareStats() {
            // Load share statistics for the modal
            $.ajax({
                url: config.ajax_url,
                type: 'POST',
                data: {
                    action: 'get_share_stats',
                    project_id: config.project_id,
                    nonce: config.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.displayShareStats(response.data);
                    }
                }
            });
        }
        
        displayShareStats(stats) {
            $('#total-shares').text(stats.total || 0);
            $('#this-week-shares').text(stats.this_week || 0);
            $('#popular-platform').text(stats.popular_platform || 'Facebook');
        }
        
        generateShareImage() {
            $.ajax({
                url: config.ajax_url,
                type: 'POST',
                data: {
                    action: 'generate_share_image',
                    project_id: config.project_id,
                    style: 'default',
                    nonce: config.nonce
                },
                beforeSend: () => {
                    $('#generate-share-image').text('Generating...').prop('disabled', true);
                },
                success: (response) => {
                    if (response.success) {
                        this.showShareImagePreview(response.data.image_url);
                    } else {
                        this.showMessage('Failed to generate share image', 'error');
                    }
                },
                complete: () => {
                    $('#generate-share-image').text('🖼️ Generate Share Image').prop('disabled', false);
                }
            });
        }
        
        showShareImagePreview(imageUrl) {
            const preview = $('<div class="share-image-preview">')
                .html(`
                    <h4>Generated Share Image:</h4>
                    <img src="${imageUrl}" alt="Share Image" style="max-width: 100%; border-radius: 8px;">
                    <div class="share-image-actions" style="margin-top: 10px;">
                        <a href="${imageUrl}" download="project-share-image.jpg" class="button">📥 Download</a>
                        <button class="button" onclick="navigator.share({files: [new File([''], 'share.jpg', {type: 'image/jpeg'})]})">📱 Share</button>
                    </div>
                `);
            
            $('.social-share-custom').append(preview);
        }
        
        createStoryTemplate() {
            // Create Instagram/Facebook story template
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            
            canvas.width = 1080;
            canvas.height = 1920;
            
            // Background gradient
            const gradient = ctx.createLinearGradient(0, 0, 0, canvas.height);
            gradient.addColorStop(0, '#667eea');
            gradient.addColorStop(1, '#764ba2');
            
            ctx.fillStyle = gradient;
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            
            // Add project image if available
            if (config.project_image) {
                const img = new Image();
                img.crossOrigin = 'anonymous';
                img.onload = () => {
                    const imgRatio = img.width / img.height;
                    const canvasRatio = canvas.width / canvas.height;
                    
                    let drawWidth, drawHeight, x, y;
                    
                    if (imgRatio > canvasRatio) {
                        drawHeight = canvas.height * 0.6;
                        drawWidth = drawHeight * imgRatio;
                        x = (canvas.width - drawWidth) / 2;
                        y = canvas.height * 0.2;
                    } else {
                        drawWidth = canvas.width * 0.8;
                        drawHeight = drawWidth / imgRatio;
                        x = (canvas.width - drawWidth) / 2;
                        y = (canvas.height - drawHeight) / 2;
                    }
                    
                    ctx.drawImage(img, x, y, drawWidth, drawHeight);
                    
                    // Add text overlay
                    this.addStoryText(ctx, canvas);
                    
                    // Convert to blob and show preview
                    canvas.toBlob((blob) => {
                        const url = URL.createObjectURL(blob);
                        this.showStoryPreview(url);
                    }, 'image/jpeg', 0.9);
                };
                img.src = config.project_image;
            } else {
                this.addStoryText(ctx, canvas);
                canvas.toBlob((blob) => {
                    const url = URL.createObjectURL(blob);
                    this.showStoryPreview(url);
                }, 'image/jpeg', 0.9);
            }
        }
        
        addStoryText(ctx, canvas) {
            // Site name
            ctx.fillStyle = 'white';
            ctx.font = 'bold 48px Arial';
            ctx.textAlign = 'center';
            ctx.fillText(config.site_name, canvas.width / 2, 150);
            
            // Project title
            ctx.font = 'bold 72px Arial';
            const title = config.project_title;
            const maxWidth = canvas.width * 0.9;
            
            this.wrapText(ctx, title, canvas.width / 2, canvas.height - 300, maxWidth, 80);
            
            // Call to action
            ctx.font = '36px Arial';
            ctx.fillText('View Project', canvas.width / 2, canvas.height - 100);
        }
        
        wrapText(ctx, text, x, y, maxWidth, lineHeight) {
            const words = text.split(' ');
            let line = '';
            
            for (let n = 0; n < words.length; n++) {
                const testLine = line + words[n] + ' ';
                const metrics = ctx.measureText(testLine);
                const testWidth = metrics.width;
                
                if (testWidth > maxWidth && n > 0) {
                    ctx.fillText(line, x, y);
                    line = words[n] + ' ';
                    y += lineHeight;
                } else {
                    line = testLine;
                }
            }
            ctx.fillText(line, x, y);
        }
        
        showStoryPreview(imageUrl) {
            const preview = $('<div class="story-preview">')
                .html(`
                    <h4>Story Template:</h4>
                    <img src="${imageUrl}" alt="Story Template" style="max-width: 200px; border-radius: 8px;">
                    <div class="story-actions" style="margin-top: 10px;">
                        <a href="${imageUrl}" download="project-story.jpg" class="button">📥 Download Story</a>
                    </div>
                `);
            
            $('.social-share-custom').append(preview);
        }
        
        generateQRCode() {
            // Simple QR code generation (in real implementation, use a proper QR library)
            const qrSize = 200;
            const qrCode = this.createQRCodeSVG(config.project_url, qrSize);
            
            $('#qr-code-display').html(qrCode);
            $('#qr-code-modal').fadeIn(300);
        }
        
        createQRCodeSVG(text, size) {
            // This is a simplified QR code - in production, use a proper QR code library
            return `
                <svg width="${size}" height="${size}" viewBox="0 0 ${size} ${size}">
                    <rect width="100%" height="100%" fill="white"/>
                    <text x="50%" y="50%" text-anchor="middle" dominant-baseline="middle" fill="black" font-size="12">
                        QR Code for:<br/>${text.substring(0, 30)}...
                    </text>
                </svg>
            `;
        }
        
        downloadQRCode() {
            const svg = document.querySelector('#qr-code-display svg');
            const svgData = new XMLSerializer().serializeToString(svg);
            const svgBlob = new Blob([svgData], { type: 'image/svg+xml;charset=utf-8' });
            const svgUrl = URL.createObjectURL(svgBlob);
            
            const downloadLink = document.createElement('a');
            downloadLink.href = svgUrl;
            downloadLink.download = 'project-qr-code.svg';
            document.body.appendChild(downloadLink);
            downloadLink.click();
            document.body.removeChild(downloadLink);
        }
        
        handleKeyboardShortcuts(e) {
            if (e.ctrlKey || e.metaKey) {
                switch (e.key) {
                    case 's':
                        e.preventDefault();
                        this.openShareModal();
                        break;
                    case 'c':
                        if ($('#social-share-modal').is(':visible')) {
                            e.preventDefault();
                            this.copyToClipboard();
                        }
                        break;
                }
            }
            
            if (e.key === 'Escape') {
                this.closeShareModal();
                this.closeQRModal();
            }
        }
        
        showMessage(message, type = 'info') {
            const messageEl = $(`
                <div class="share-message ${type}" style="
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: ${type === 'success' ? '#4caf50' : type === 'error' ? '#f44336' : '#2196f3'};
                    color: white;
                    padding: 15px 20px;
                    border-radius: 8px;
                    z-index: 100001;
                    animation: slideInRight 0.3s ease;
                ">
                    ${message}
                </div>
            `);
            
            $('body').append(messageEl);
            
            setTimeout(() => {
                messageEl.fadeOut(300, () => messageEl.remove());
            }, 3000);
        }
    }
    
    // Initialize when document is ready
    $(document).ready(() => {
        if (typeof projectGallerySocial !== 'undefined') {
            const socialManager = new SocialShareManager();
            
            // Expose for external access
            window.projectGallerySocialManager = socialManager;
            
            console.log('Project Gallery Social: Initialized');
        }
    });
    
})(jQuery);