# Plugin Features Demo

## 🖼️ Gallery Grid View
```
[Project 1 Image] [Project 2 Image] [Project 3 Image]
   Modern Villa      Office Design     Garden Landscape
   Mimari            İç Tasarım        Peyzaj

[Project 4 Image] [Project 5 Image] [Project 6 Image]
   Hotel Complex     Cafe Interior     Park Design
   Mimari            İç Tasarım        Peyzaj
```

## 📱 Single Project Page
```
=== Modern Villa Projesi ===
[Mimari] [Lüks] [Modern]

Villa açıklaması burada yer alacak...

📸 PROJE GALERİSİ:
[Img1] [Img2] [Img3]
[Img4] [Img5] [Img6]

← Önceki Proje | Tüm Projeler | Sonraki Proje →
```

## 🔍 Lightbox View
```
                    ╔═══════════════════════════════╗
                    ║              ×                ║
                    ║    ◀    [BÜYÜK RESİM]    ▶   ║
                    ║         3 / 6                 ║
                    ╚═══════════════════════════════╝
```

## 📊 Admin Panel
```
WordPress Admin > Projeler
├── Tüm Projeler (12)
├── Yeni Proje Ekle
├── Kategoriler
│   ├── Mimari (5)
│   ├── İç Tasarım (4)
│   └── Peyzaj (3)
└── Etiketler
```

## 📝 Shortcode Examples
```html
<!-- Tüm projeler -->
[proje_galerisi]

<!-- Sadece mimari projeleri -->
[proje_galerisi kategori="mimari"]

<!-- 6 proje, 2 sütun -->
[proje_galerisi limit="6" columns="2"]

<!-- İç tasarım projeleri, 4 sütun -->
[proje_galerisi kategori="ic-tasarim" columns="4"]
```

## 🎨 CSS Classes for Customization
```css
.project-gallery              /* Main gallery container */
.project-item                 /* Individual project */
.project-thumbnail            /* Project image area */
.project-overlay              /* Hover overlay */
.lightbox                     /* Lightbox container */
.single-project-gallery       /* Single page gallery */
.project-navigation           /* Prev/next navigation */
```

## ⚡ Performance Features
- ✅ Lazy loading images
- ✅ Optimized image sizes (thumbnail, medium, large)
- ✅ Minimal CSS/JS footprint
- ✅ AJAX image loading
- ✅ Browser caching support