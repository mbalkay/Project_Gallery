# Project Gallery WordPress Plugin

WordPress proje galerisi eklentisi - projelerinizi kategorilere ayırarak görsel galeri olarak sunmanızı sağlar.

## Özellikler

### 🎯 Ana Özellikler
- **Özel Yazı Tipi**: 'Projeler' adında özel post type
- **Kategoriler**: 'Proje Kategorileri' adında özel taksonomi
- **Öne Çıkan Görsel**: Her proje için featured image desteği
- **Proje Galerisi**: Her projenin kendi fotoğraf galerisi
- **Kısa Kod**: `[proje_galerisi]` shortcode ile kolay entegrasyon
- **Tekil Proje Sayfaları**: Detaylı proje görüntüleme
- **Lightbox Galerisi**: Resim büyütme ve navigasyon
- **Proje Navigasyonu**: Kategoriye göre proje geçişi
- **Responsive Tasarım**: Tüm cihazlarda uyumlu

### 📱 Responsive Özellikler
- Mobil, tablet ve masaüstü uyumlu
- Touch/swipe desteği lightbox için
- Adaptive grid layout
- Retina display desteği

### ⚡ Performans
- Lazy loading desteği
- Optimized resim boyutları
- AJAX ile asenkron yükleme
- Minimal CSS/JS footprint

## Kurulum

1. Plugin dosyalarını `/wp-content/plugins/project-gallery/` klasörüne yükleyin
2. WordPress admin panelinden eklentiyi aktifleştirin
3. Permalink ayarlarını yenilemek için "Ayarlar > Kalıcı Bağlantılar" sayfasını ziyaret edin

## Kullanım

### Proje Ekleme
1. Admin panelde "Projeler > Yeni Proje Ekle" sayfasına gidin
2. Proje başlığını ve açıklamasını girin
3. Öne çıkan görsel seçin
4. "Proje Galerisi" meta box'ından galeri resimlerini seçin
5. Proje kategorilerini atayın
6. Projeyi yayınlayın

### Shortcode Kullanımı

#### Tüm projeleri göster:
```
[proje_galerisi]
```

#### Belirli kategorideki projeleri göster:
```
[proje_galerisi kategori="mimari"]
```

#### Özelleştirme parametreleri:
```
[proje_galerisi kategori="mimari" limit="6" columns="3"]
```

**Parametreler:**
- `kategori`: Proje kategorisi slug'ı
- `limit`: Gösterilecek proje sayısı (varsayılan: -1, tümü)
- `columns`: Sütun sayısı (varsayılan: 3)

### Tema Entegrasyonu

Plugin otomatik olarak template dosyalarını kullanır:
- `single-proje.php`: Tekil proje sayfaları için
- `archive-proje.php`: Proje arşiv sayfaları için

Temanızda özelleştirmek isterseniz, bu dosyaları tema klasörünüze kopyalayın.

## Dosya Yapısı

```
project-gallery/
├── project-gallery.php          # Ana plugin dosyası
├── assets/
│   ├── css/
│   │   └── project-gallery.css  # Stil dosyası
│   └── js/
│       └── project-gallery.js   # JavaScript dosyası
├── templates/
│   ├── single-proje.php         # Tekil proje şablonu
│   └── archive-proje.php        # Arşiv sayfası şablonu
└── README.md                    # Bu dosya
```

## CSS Sınıfları

### Galeri Sınıfları
- `.project-gallery`: Ana galeri container
- `.project-item`: Tekil proje öğesi
- `.project-thumbnail`: Proje resim alanı
- `.project-overlay`: Hover overlay
- `.project-title`: Proje başlığı
- `.project-categories`: Kategori listesi

### Lightbox Sınıfları
- `.lightbox`: Lightbox container
- `.lightbox-content`: İçerik alanı
- `.lightbox-image`: Ana resim
- `.lightbox-nav`: Navigasyon butonları
- `.lightbox-close`: Kapatma butonu

### Responsive Sınıfları
Plugin otomatik olarak ekran boyutuna göre grid layout'u ayarlar.

## Tarayıcı Desteği

- Chrome 60+
- Firefox 60+
- Safari 12+
- Edge 79+
- iOS Safari 12+
- Android Chrome 60+

## Geliştirme

### Özelleştirme Hook'ları

```php
// Galeri shortcode özelleştirme
add_filter('project_gallery_shortcode_args', function($args) {
    // $args dizisini özelleştir
    return $args;
});

// Lightbox ayarları
add_filter('project_gallery_lightbox_config', function($config) {
    // $config dizisini özelleştir
    return $config;
});
```

### JavaScript API

```javascript
// Lightbox'ı programatik olarak aç
ProjectGallery.openLightbox(images, startIndex);

// Lightbox'ı kapat
ProjectGallery.closeLightbox();
```

## Sorun Giderme

### Resimler gösterilmiyor
1. WordPress media kütüphanesinin düzgün çalıştığından emin olun
2. Dosya izinlerini kontrol edin
3. Plugin'i deaktif edip tekrar aktif edin

### Lightbox çalışmıyor
1. Tarayıcı konsolunda JavaScript hatalarını kontrol edin
2. jQuery'nin yüklendiğinden emin olun
3. Tema ile çakışma olup olmadığını kontrol edin

### Stil sorunları
1. Tema CSS'i ile çakışma olup olmadığını kontrol edin
2. CSS öncelik sorunları için `!important` kullanabilirsiniz
3. Tarayıcı cache'ini temizleyin

## Changelog

### 1.0.0
- İlk sürüm
- Temel galeri işlevselliği
- Lightbox özelliği
- Responsive tasarım
- Admin panel entegrasyonu

## Lisans

GPL v2 or later

## Destek

Herhangi bir sorun yaşarsanız veya öneriniz varsa GitHub repository'sindeki Issues bölümünü kullanabilirsiniz.