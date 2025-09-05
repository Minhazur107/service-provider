<?php
/**
 * Image utilities for automatic image handling on homepage
 * - Ensures directories exist
 * - Picks random images from upload/default directories
 */

// Ensure required directories exist at include time
function ensureImageDirs() {
    $dirs = [
        'uploads/hero',
        'uploads/categories',
        'assets/images/default/hero',
        'assets/images/default/categories'
    ];
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }
}
ensureImageDirs();

/**
 * Return random image path (relative) from a directory.
 * Allowed extensions: jpg, jpeg, png, webp, svg
 */
function getRandomImage(string $directory): string {
    if (!is_dir($directory)) {
        return '';
    }
    $images = [];
    // scandir returns . and .. also
    foreach (@scandir($directory) ?: [] as $file) {
        if ($file === '.' || $file === '..') continue;
        $path = rtrim($directory, '/\\') . '/' . $file;
        if (is_file($path)) {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp','svg'], true)) {
                $images[] = $path;
            }
        }
    }
    if (!$images) return '';
    return $images[array_rand($images)];
}

/**
 * Basic slugify for category names (handles EN/BN by simplifying to lowercase and replacing spaces/symbols)
 */
function slugify_name(string $name): string {
    $s = strtolower(trim($name));
    // Replace non letters/digits with space
    $s = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $s);
    $s = preg_replace('/\s+/', '-', trim($s));
    return $s ?: 'category';
}

/**
 * Map common category name slugs to default images under assets/images/default/categories
 */
function mapCategoryDefaultByName(string $name): string {
    $slug = slugify_name($name);
    $map = [
        // English
        'ac-servicing' => 'ac-service.svg',
        'ac' => 'ac-service.svg',
        'ac-repair' => 'ac-service.svg',
        'air-conditioning' => 'ac-service.svg',
        'plumbing' => 'plumbing.svg',
        'electrical' => 'electrical.svg',
        'cleaning' => 'cleaning.svg',
        'carpentry' => 'carpentry.svg',
        'painting' => 'painting.svg',
        'moving' => 'moving.svg',
        'gardening' => 'landscaping.svg',
        'landscaping' => 'landscaping.svg',
        'security' => 'security.svg',
        'roofing' => 'roofing.svg',
        'maintenance' => 'tools.svg',
        'repair' => 'tools.svg',
        'installation' => 'tools.svg',
        // Common Bengali equivalents (rough slugs)
        'এসি-সার্ভিসিং' => 'ac-service.svg',
        'এসি' => 'ac-service.svg',
        'প্লাম্বিং' => 'plumbing.svg',
        'ইলেক্ট্রিক্যাল' => 'electrical.svg',
        'পরিষ্কার-পরিচ্ছন্নতা' => 'cleaning.svg',
        'কাঠমিস্ত্রি' => 'carpentry.svg',
        'রং' => 'painting.svg',
        'স্থানান্তর' => 'moving.svg',
        'বাগান' => 'landscaping.svg',
        'নিরাপত্তা' => 'security.svg',
        'ছাদ' => 'roofing.svg',
        'মেরামত' => 'tools.svg',
    ];

    if (isset($map[$slug])) {
        $path = 'assets/images/default/categories/' . $map[$slug];
        if (is_file($path)) return $path;
    }

    // Fallback to any default category image
    $fallback = getRandomImage('assets/images/default/categories');
    return $fallback ?: '';
}

/**
 * Get a random hero image, falling back to defaults when empty.
 */
function getHeroImage(): string {
    return getRandomImage('uploads/hero')
        ?: getRandomImage('assets/images/default/hero')
        ?: '';
}

/**
 * Get a random category image for a specific category id.
 * Looks in uploads/categories/{id} first, then default category images.
 */
function getCategoryImage(int $categoryId): string {
    $specific = 'uploads/categories/' . $categoryId;
    $img = getRandomImage($specific);
    if ($img) return $img;

    // Fallback pool in defaults
    $fallback = getRandomImage('assets/images/default/categories');
    return $fallback ?: '';
}

/**
 * Get category image by name (for better defaults like AC Servicing, Plumbing, etc.)
 */
function getCategoryImageByName(string $name): string {
    // Try name-based mapping first (specific default per category)
    $byName = mapCategoryDefaultByName($name);
    if ($byName) return $byName;

    // Otherwise any default
    return getRandomImage('assets/images/default/categories') ?: '';
}
