<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AnimalPhoto;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class PhotoUploader
{
    public function __construct(
        private readonly string $uploadDir,
        private readonly string $publicPath,
    ) {
    }

    public function upload(UploadedFile $file): AnimalPhoto
    {
        if (!\function_exists('imagewebp')) {
            throw new \RuntimeException('L\'extension GD avec support WebP est requise.');
        }

        if (!is_dir($this->uploadDir) && !mkdir($this->uploadDir, 0775, true) && !is_dir($this->uploadDir)) {
            throw new \RuntimeException('Impossible de créer le dossier d\'upload.');
        }

        $contents = (string) file_get_contents($file->getPathname());
        $image = @imagecreatefromstring($contents);
        if (false === $image) {
            throw new \RuntimeException('Le fichier envoyé n\'est pas une image valide.');
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $max = 1600;
        if ($width > $max || $height > $max) {
            $ratio = min($max / $width, $max / $height);
            $scaled = imagescale($image, max(1, (int) round($width * $ratio)), max(1, (int) round($height * $ratio)));
            if (false === $scaled) {
                imagedestroy($image);
                throw new \RuntimeException('Le redimensionnement de l\'image a échoué.');
            }
            imagedestroy($image);
            $image = $scaled;
            $width = imagesx($image);
            $height = imagesy($image);
        }

        $filename = bin2hex(random_bytes(12)).'.webp';
        $target = $this->uploadDir.'/'.$filename;

        if (!imagewebp($image, $target, 88)) {
            throw new \RuntimeException('La conversion de l\'image en WebP a échoué.');
        }

        imagedestroy($image);

        return (new AnimalPhoto())
            ->setFilename($this->publicPath.'/'.$filename)
            ->setOriginalFilename($file->getClientOriginalName())
            ->setMimeType('image/webp')
            ->setWidth($width)
            ->setHeight($height)
            ->setSize((int) filesize($target));
    }

    public function delete(AnimalPhoto $photo): void
    {
        $relative = str_replace($this->publicPath.'/', '', $photo->getFilename());
        $path = $this->uploadDir.'/'.$relative;
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
