# Project Gallery Plugin - Installation & Test Guide

## Quick Installation

1. **Upload Plugin:**
   - Zip the entire `Project_Gallery` folder
   - Upload via WordPress Admin > Plugins > Add New > Upload Plugin
   - Or copy files to `/wp-content/plugins/project-gallery/`

2. **Activate Plugin:**
   - Go to WordPress Admin > Plugins
   - Find "Project Gallery" and click "Activate"

3. **Update Permalinks:**
   - Go to Settings > Permalinks
   - Click "Save Changes" to refresh rewrite rules

## Testing the Plugin

### 1. Create Project Categories
- Go to **Projeler > Kategoriler**
- Add categories like: "Mimari", "İç Tasarım", "Peyzaj"

### 2. Add Sample Projects
- Go to **Projeler > Yeni Proje Ekle**
- Add title: "Modern Villa Projesi"
- Add content description
- Set featured image
- In "Proje Galerisi" meta box, select 3-5 images
- Assign to "Mimari" category
- Publish

### 3. Test Shortcode
Add to any page/post:
```
[proje_galerisi]
```

Or with category filter:
```
[proje_galerisi kategori="mimari"]
```

### 4. Test Features
- ✅ **Gallery Display**: Check if projects show in grid
- ✅ **Category Filter**: Test shortcode with category parameter
- ✅ **Single Project**: Click project to view single page
- ✅ **Lightbox**: Click gallery images to open lightbox
- ✅ **Navigation**: Use arrows in lightbox and project navigation
- ✅ **Responsive**: Test on mobile/tablet
- ✅ **Touch Support**: Swipe in lightbox on mobile

## Expected URLs
- Archive: `/proje/`
- Category: `/proje-kategorisi/mimari/`
- Single: `/proje/modern-villa-projesi/`

## Troubleshooting

### Permalinks Issue
If single projects show 404:
- Go to Settings > Permalinks
- Click "Save Changes"

### Images Not Showing
- Check file permissions on uploads folder
- Verify images exist in Media Library

### Lightbox Not Working
- Check browser console for JavaScript errors
- Ensure jQuery is loaded
- Check for theme conflicts

## Demo Content Structure
```
Project: "Modern Villa Projesi"
├── Featured Image: villa-exterior.jpg
├── Gallery Images:
│   ├── villa-interior-1.jpg
│   ├── villa-interior-2.jpg
│   ├── villa-garden.jpg
│   └── villa-pool.jpg
└── Category: "Mimari"
```

## Success Criteria
- [x] Projects display in admin panel
- [x] Categories are manageable
- [x] Gallery meta box works with media library
- [x] Shortcode displays projects
- [x] Single project pages work
- [x] Lightbox functionality works
- [x] Navigation between projects works
- [x] Responsive design works on mobile
- [x] Touch/swipe works in lightbox