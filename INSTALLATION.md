# Installation & Setup Guide

## 🚀 Quick Installation

### Method 1: WordPress Admin Upload
1. Download the plugin ZIP file from CodeCanyon
2. Go to **WordPress Admin > Plugins > Add New**
3. Click **Upload Plugin** and select the ZIP file
4. Click **Install Now** and then **Activate Plugin**

### Method 2: FTP Upload
1. Extract the ZIP file to your computer
2. Upload the `project-gallery` folder to `/wp-content/plugins/`
3. Go to **WordPress Admin > Plugins**
4. Find "Project Gallery Pro" and click **Activate**

### Method 3: File Manager
1. Access your hosting control panel (cPanel, Plesk, etc.)
2. Navigate to **File Manager > public_html/wp-content/plugins/**
3. Upload and extract the plugin ZIP file
4. Activate the plugin from WordPress admin

## ⚙️ Initial Configuration

### Step 1: Basic Settings
1. Navigate to **Projects > Gallery Settings**
2. Configure basic layout options:
   - Layout Type: Grid, Masonry, Justified, or Flexible
   - Number of columns for desktop, tablet, and mobile
   - Image spacing and border radius
   - Hover effects and animations

### Step 2: Performance Optimization
1. Go to **Projects > Performance Settings**
2. Enable recommended features:
   - ✅ Lazy Loading
   - ✅ WebP Support (if server supports it)
   - ✅ Progressive Loading
   - ✅ Caching System
   - ✅ Connection-Aware Loading

### Step 3: Analytics Configuration
1. Navigate to **Projects > Analytics**
2. Enable analytics tracking:
   - ✅ Visitor Analytics
   - ✅ Engagement Tracking
   - ✅ Performance Monitoring
   - ✅ Popular Content Analysis

### Step 4: Social Media Setup
1. Go to **Projects > Social Settings**
2. Configure sharing options:
   - Select platforms to enable
   - Customize share messages
   - Set up branded share images
   - Configure QR code generation

## 📝 Creating Your First Project

### Step 1: Add New Project
1. Go to **Projects > Add New Project**
2. Enter project title and description
3. Set featured image (recommended: 1200x800px)
4. Select or create project categories

### Step 2: Configure Project Gallery
1. Scroll down to **Project Gallery** meta box
2. Click **Add Images** to select multiple photos
3. Drag and drop to reorder images
4. Add captions and alt text for SEO

### Step 3: Add Video Content (Optional)
1. Find the **Video Gallery** meta box
2. Click **Add Video** to add YouTube, Vimeo, or MP4 videos
3. Enter video URL or upload MP4 file
4. Add video title and description

### Step 4: Publish Project
1. Preview your project to check layout
2. Click **Publish** to make it live
3. View the project on frontend to test functionality

## 🎨 Display Options

### Using Shortcodes

#### Basic Gallery Display
```php
[proje_galerisi]
```

#### Filtered by Category
```php
[proje_galerisi kategori="architecture"]
```

#### Custom Layout
```php
[proje_galerisi columns="4" limit="12"]
```

#### Video Gallery
```php
[proje_video_galerisi id="123" columns="3"]
```

#### Search Widget
```php
[project_search show_filters="true" style="modern"]
```

#### Social Sharing
```php
[project_social_share platforms="facebook,twitter,linkedin"]
```

### Template Integration

#### In Theme Files
```php
// Display project gallery in any template
if (function_exists('project_gallery_display')) {
    project_gallery_display(array(
        'category' => 'portfolio',
        'columns' => 3,
        'limit' => 9
    ));
}
```

#### Widget Areas
1. Go to **Appearance > Widgets**
2. Find "Project Gallery Widget"
3. Drag to desired widget area
4. Configure display options

## 🔧 Advanced Configuration

### Custom CSS Styling
Add custom styles in **Appearance > Customize > Additional CSS**:

```css
/* Customize gallery container */
.project-gallery {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 10px;
}

/* Custom hover effects */
.project-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

/* Mobile optimizations */
@media (max-width: 768px) {
    .project-gallery {
        padding: 10px;
    }
}
```

### PHP Customizations
Add to your theme's `functions.php`:

```php
// Customize gallery output
add_filter('project_gallery_html', function($html, $projects) {
    // Your custom modifications
    return $html;
}, 10, 2);

// Modify search results
add_filter('project_gallery_search_results', function($results) {
    // Custom search result modifications
    return $results;
});

// Add custom analytics tracking
add_action('project_gallery_image_viewed', function($image_id, $project_id) {
    // Your custom analytics code
});
```

### Database Optimization
For large galleries, consider these optimizations:

```php
// Increase WordPress memory limit
ini_set('memory_limit', '256M');

// Optimize database queries
add_filter('project_gallery_query_args', function($args) {
    $args['cache_results'] = true;
    $args['update_post_meta_cache'] = false;
    return $args;
});
```

## 🛡️ Security Configuration

### File Upload Security
Configure allowed file types in **Projects > Security Settings**:
- Image formats: JPG, PNG, WebP, GIF
- Video formats: MP4, WebM, OGV
- Maximum file size: 10MB (adjustable)

### User Permissions
Set up user roles and capabilities:
- **Administrator**: Full access to all features
- **Editor**: Can manage projects and settings
- **Author**: Can create and edit own projects
- **Contributor**: Can create projects (pending review)

### Privacy Settings
Configure GDPR compliance:
- Enable anonymized analytics
- Set up cookie consent notices
- Configure data retention periods
- Enable user data export/deletion

## 📱 Mobile Optimization

### Responsive Breakpoints
The plugin uses these responsive breakpoints:
- **Desktop**: 1200px and above
- **Laptop**: 992px - 1199px
- **Tablet**: 768px - 991px
- **Mobile**: 767px and below

### Touch Optimization
Enable touch-friendly features:
- Larger touch targets (minimum 44px)
- Swipe gestures for gallery navigation
- Touch feedback and animations
- Optimized loading for mobile networks

### PWA Features
Enable Progressive Web App features:
- Service worker for offline functionality
- Web app manifest for mobile installation
- Push notifications for new projects
- Background sync for analytics

## 🔍 SEO Optimization

### Search Engine Setup
1. Enable **SEO Features** in plugin settings
2. Configure structured data markup
3. Set up XML sitemaps for projects
4. Optimize meta descriptions and titles

### Image SEO
- Use descriptive file names
- Add alt text to all images
- Optimize image sizes (WebP preferred)
- Include captions with keywords

### Content Optimization
- Write detailed project descriptions
- Use relevant keywords in titles
- Create category-specific landing pages
- Implement internal linking strategy

## 📊 Analytics Setup

### Google Analytics Integration
1. Install Google Analytics on your site
2. Enable enhanced tracking in plugin settings
3. Set up custom events for gallery interactions
4. Configure conversion tracking

### Built-in Analytics
The plugin provides comprehensive analytics:
- Page views and unique visitors
- Image interaction tracking
- Popular content identification
- Device and browser statistics
- Performance monitoring

### Custom Tracking
Add custom tracking events:

```javascript
// Track custom interactions
document.addEventListener('projectGalleryImageClick', function(event) {
    gtag('event', 'gallery_interaction', {
        'event_category': 'engagement',
        'event_label': event.detail.imageSrc,
        'value': 1
    });
});
```

## 🚀 Performance Tuning

### Server Requirements
Ensure your server meets these requirements:
- **PHP**: 7.4+ (8.0+ recommended)
- **MySQL**: 5.6+ (8.0+ recommended)
- **Memory**: 128MB+ (256MB+ recommended)
- **Storage**: 50MB+ for plugin files

### Optimization Tips
1. **Enable Caching**: Use caching plugins like WP Rocket or W3 Total Cache
2. **Use CDN**: Integrate with CloudFlare or similar CDN service
3. **Optimize Database**: Use plugins like WP-Optimize for database cleanup
4. **Compress Images**: Enable WebP conversion and image compression
5. **Minify Assets**: Use minification plugins for CSS/JS optimization

### Performance Monitoring
Monitor performance with built-in tools:
- Page load time analysis
- Database query optimization
- Image loading performance
- User experience metrics

## 🆘 Troubleshooting

### Common Issues

#### Images Not Displaying
**Possible Causes:**
- Incorrect file permissions
- Memory limit exceeded
- Incompatible image format

**Solutions:**
1. Set folder permissions to 755, files to 644
2. Increase PHP memory limit to 256MB
3. Convert images to supported formats (JPG, PNG, WebP)

#### Slow Loading Times
**Possible Causes:**
- Large image files
- Too many projects loading at once
- Server performance issues

**Solutions:**
1. Enable lazy loading in performance settings
2. Reduce number of projects per page
3. Optimize images (use WebP format)
4. Enable caching system

#### Search Not Working
**Possible Causes:**
- Search index not built
- Database permissions
- AJAX conflicts

**Solutions:**
1. Rebuild search index in admin panel
2. Check database write permissions
3. Disable other plugins temporarily to identify conflicts

#### Mobile Display Issues
**Possible Causes:**
- Theme conflicts
- CSS overrides
- Viewport settings

**Solutions:**
1. Check theme compatibility
2. Add custom CSS for mobile
3. Verify viewport meta tag in theme

### Debug Mode
Enable debug mode for troubleshooting:

```php
// Add to wp-config.php
define('PROJECT_GALLERY_DEBUG', true);
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### Getting Support
For additional help:
1. Check the **FAQ** section in plugin settings
2. Review **documentation** at [documentation-url]
3. Contact **support** through CodeCanyon message system
4. Join our **community forum** for user discussions

## ✅ Launch Checklist

Before going live, verify:
- [ ] All images are optimized and loading correctly
- [ ] Mobile responsiveness is working properly
- [ ] Social sharing buttons are functional
- [ ] Analytics tracking is active
- [ ] SEO elements are properly configured
- [ ] Performance optimization is enabled
- [ ] Backup system is in place
- [ ] Security settings are configured
- [ ] User permissions are set correctly
- [ ] All features are tested across devices

---

*Need help? Our support team is ready to assist you with any installation or configuration questions!*