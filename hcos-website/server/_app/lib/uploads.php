<?php
declare(strict_types=1);

/* ---------- file uploads ---------------------------------------------------------------
   Rules, applied to every upload whether from the admin or an attendee:
   - the real type is sniffed with finfo, never taken from the filename
   - images are RE-ENCODED to WebP (Imagick, else GD): metadata gone, any
     payload hidden in the original gone, size bounded
   - PDFs must start with %PDF- and are refused if they carry JavaScript,
     launch actions or embedded files
   - public assets use uploads/<event-slug>/<kind>/ with script execution denied;
     registration headshots use private/headshots/event-<id>/
   - deletion stays within these owned upload directories                         */

class UploadException extends RuntimeException {}

const HCOS_IMAGE_MIMES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

// Compression: every image is re-encoded to WebP at this quality and capped to
// a maximum edge. 85 is visually indistinguishable from the original for
// photographs while typically cutting a phone JPG to a quarter of its size.
const HCOS_WEBP_QUALITY = 85;
// Public (attendee) limits. Admin uploads allow larger originals; both are compressed.
const HCOS_PUBLIC_IMAGE_MAX = 3 * 1024 * 1024;   // 3 MB
const HCOS_PUBLIC_PDF_MAX = 10 * 1024 * 1024;    // 10 MB

/** Normalise $_FILES[field] (single or multiple) into a list of file arrays. */
function files_list(?array $f): array {
  if (!$f || !isset($f['name'])) return [];
  if (!is_array($f['name'])) return $f['error'] === UPLOAD_ERR_NO_FILE ? [] : [$f];
  $out = [];
  foreach ($f['name'] as $i => $name) {
    if (($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
    $out[] = ['name' => $name, 'type' => $f['type'][$i] ?? '', 'tmp_name' => $f['tmp_name'][$i] ?? '', 'error' => $f['error'][$i] ?? 0, 'size' => $f['size'][$i] ?? 0];
  }
  return $out;
}

function upload_check(array $file, int $maxBytes): void {
  $err = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
  if ($err !== UPLOAD_ERR_OK) {
    throw new UploadException(match ($err) {
      UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'That file is too large.',
      UPLOAD_ERR_NO_FILE => 'No file was chosen.',
      default => 'The upload did not complete. Please try again.',
    });
  }
  if (!is_uploaded_file($file['tmp_name'])) throw new UploadException('Invalid upload.');
  if ((int)$file['size'] > $maxBytes) throw new UploadException('That file is ' . fmt_bytes((int)$file['size']) . ' — the limit is ' . fmt_bytes($maxBytes) . '.');
  if ((int)$file['size'] < 32) throw new UploadException('That file is empty.');
}

function sniff_mime(string $path): string {
  $fi = new finfo(FILEINFO_MIME_TYPE);
  return (string)($fi->file($path) ?: 'application/octet-stream');
}

/** Destination directory for an event asset kind; created on demand. */
function upload_dir(string $slug, string $kind): array {
  global $HCOS;
  if (!valid_slug($slug) || !preg_match('/^[a-z]+$/', $kind)) throw new UploadException('Bad destination.');
  if ($kind === 'headshots') {
    $rel = 'headshots/' . $slug;
    $abs = HCOS_PRIVATE . '/' . $rel;
    if (!is_dir($abs) && !@mkdir($abs, 0700, true)) throw new UploadException('Photo storage could not be created.');
    return [$abs, $rel];
  }
  $rel = $HCOS['uploads_url'] . '/' . $slug . '/' . $kind;
  $abs = rtrim((string)$HCOS['uploads_dir'], '/') . '/' . $slug . '/' . $kind;
  if (!is_dir($abs) && !@mkdir($abs, 0755, true)) throw new UploadException('Storage folder could not be created.');
  return [$abs, $rel];
}

/**
 * Validate + re-encode an image to WebP.
 * @return array{path:string,mime:string,width:int,height:int}
 */
function store_image(array $file, string $slug, string $kind, int $maxDim = 2000, int $maxBytes = 15728640): array {
  upload_check($file, $maxBytes);
  $mime = sniff_mime($file['tmp_name']);
  if (!isset(HCOS_IMAGE_MIMES[$mime])) throw new UploadException('Only JPG, PNG or WebP images are accepted.');
  $info = @getimagesize($file['tmp_name']);
  if (!$info || $info[0] < 16 || $info[1] < 16) throw new UploadException('That image could not be read.');
  if ($info[0] * $info[1] > 60_000_000) throw new UploadException('That image has too many pixels.');

  [$abs, $rel] = upload_dir($slug, $kind);
  $name = random_token(8) . '.webp';
  $dest = $abs . '/' . $name;

  if (class_exists('Imagick')) {
    $im = new Imagick($file['tmp_name']);
    if (method_exists($im, 'autoOrient')) $im->autoOrient();
    if (method_exists($im, 'transformImageColorspace')) $im->transformImageColorspace(Imagick::COLORSPACE_SRGB); // CMYK print JPGs -> sRGB
    $im->stripImage();
    if ($im->getImageWidth() > $maxDim || $im->getImageHeight() > $maxDim) $im->thumbnailImage($maxDim, $maxDim, true);
    $im->setImageFormat('webp');
    $im->setImageCompressionQuality(HCOS_WEBP_QUALITY);
    $w = $im->getImageWidth(); $h = $im->getImageHeight();
    $ok = $im->writeImage($dest);
    $im->clear();
    if (!$ok) throw new UploadException('The image could not be saved.');
  } else {
    $src = @imagecreatefromstring((string)file_get_contents($file['tmp_name']));
    if (!$src) throw new UploadException('That image could not be read.');
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
      $exif = @exif_read_data($file['tmp_name']);
      $o = (int)($exif['Orientation'] ?? 1);
      $rot = match ($o) { 3 => 180, 6 => -90, 8 => 90, default => 0 };
      if ($rot) { $r = imagerotate($src, $rot, 0); if ($r) { imagedestroy($src); $src = $r; } }
    }
    $w = imagesx($src); $h = imagesy($src);
    if ($w > $maxDim || $h > $maxDim) {
      $scale = min($maxDim / $w, $maxDim / $h);
      $nw = max(1, (int)round($w * $scale)); $nh = max(1, (int)round($h * $scale));
      $dst = imagecreatetruecolor($nw, $nh);
      imagealphablending($dst, false); imagesavealpha($dst, true);
      imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
      imagedestroy($src); $src = $dst; $w = $nw; $h = $nh;
    }
    imagepalettetotruecolor($src);
    $ok = imagewebp($src, $dest, HCOS_WEBP_QUALITY);
    imagedestroy($src);
    if (!$ok) throw new UploadException('The image could not be saved.');
  }
  @chmod($dest, $kind === 'headshots' ? 0600 : 0644);
  return ['path' => $rel . '/' . $name, 'mime' => 'image/webp', 'width' => (int)$w, 'height' => (int)$h];
}

/** Validate and store a PDF as-is. */
function store_pdf(array $file, string $slug, string $kind, int $maxBytes = 26214400): array {
  upload_check($file, $maxBytes);
  if (sniff_mime($file['tmp_name']) !== 'application/pdf') throw new UploadException('Only PDF documents are accepted here.');
  $fh = fopen($file['tmp_name'], 'rb');
  $head = $fh ? (string)fread($fh, 8) : '';
  if ($fh) fclose($fh);
  if (!str_starts_with($head, '%PDF-')) throw new UploadException('That is not a valid PDF.');
  $body = (string)file_get_contents($file['tmp_name']);
  // An explicit opening page/zoom destination is navigation, not executable content.
  $scan = preg_replace('~/OpenAction(?=\s*\[\s*\d+\s+\d+\s+R\s+/(?:Fit(?:B[HV]?|[HVR])?|XYZ)(?:\s+(?:[+-]?(?:\d+(?:\.\d*)?|\.\d+)|null))*\s*\])~', '/InitialView', $body);
  if ($scan === null || preg_match('~/(JavaScript|JS|Launch|EmbeddedFile|OpenAction|AA)\b~', $scan)) {
    throw new UploadException('This PDF contains scripts or embedded files and cannot be accepted. Please export a plain PDF.');
  }
  [$abs, $rel] = upload_dir($slug, $kind);
  $name = random_token(8) . '.pdf';
  $dest = $abs . '/' . $name;
  if (!move_uploaded_file($file['tmp_name'], $dest)) throw new UploadException('The document could not be saved.');
  @chmod($dest, 0644);
  return ['path' => $rel . '/' . $name, 'mime' => 'application/pdf'];
}

/** Image or PDF, decided by the sniffed type. */
function store_image_or_pdf(array $file, string $slug, string $kind, int $maxDim = 2000, int $maxImageBytes = 15728640, int $maxPdfBytes = 26214400): array {
  upload_check($file, max($maxImageBytes, $maxPdfBytes));
  $mime = sniff_mime($file['tmp_name']);
  if ($mime === 'application/pdf') return store_pdf($file, $slug, $kind, $maxPdfBytes);
  if (isset(HCOS_IMAGE_MIMES[$mime])) return store_image($file, $slug, $kind, $maxDim, $maxImageBytes);
  throw new UploadException('Only JPG, PNG, WebP or PDF files are accepted.');
}

/** Remove an owned public upload or a private registration headshot. */
function delete_upload(?string $rel): void {
  global $HCOS;
  if (!$rel) return;
  if (str_contains($rel, '..')) return;
  if (str_starts_with($rel, 'headshots/')) {
    $abs = HCOS_PRIVATE . '/' . $rel;
    $base = realpath(HCOS_PRIVATE . '/headshots');
  } elseif (str_starts_with($rel, $HCOS['uploads_url'] . '/')) {
    $abs = HCOS_ROOT . '/' . $rel;
    $base = realpath((string)$HCOS['uploads_dir']);
  } else return;
  $real = realpath($abs);
  if ($base && $real && str_starts_with($real, $base . DIRECTORY_SEPARATOR) && is_file($real)) @unlink($real);
}

/** Safe display name for an attendee's original filename. */
function clean_filename(string $name): string {
  $name = preg_replace('/[^\w .()-]+/u', '_', basename($name)) ?? 'file';
  return mb_substr(trim($name, ' ._') ?: 'file', 0, 120);
}
