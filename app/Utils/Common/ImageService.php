<?php
/**
 * Created by PhpStorm.
 * User: arash
 * Date: 8/8/16
 * Time: 12:34 AM
 */

namespace App\Utils\Common;


use App\Jobs\ImageResizer;
use App\Models\Interfaces\ImageContract;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ImageService
{
    public static function getImageConfig($category, $size = null)
    {
        if (isset($size))
            return config("cms.images.{$category}.{$size}");
        else
            return config("cms.images.{$category}");
    }

    public static function getImage(ImageContract $model=null, $type = 'original', $absolute = false)
    {
        if($model == null)
            return '';

        if(!$model->isImageLocal())
            return $model->getImagePath();

        $prefix = ($absolute ? env('APP_URL') : '');
        if ($model->hasImage()) {
            $imagePath = $model->getImagePath();
            $lastDotPosition = strrpos($imagePath, '.');
            $resultImage = substr_replace($imagePath, '-'.$type, $lastDotPosition, 0);

            if (file_exists(public_path() . $resultImage))
                return $prefix . $resultImage;
            else if (file_exists(public_path() . $imagePath))
                return $prefix . $imagePath;
        }
        return $prefix . $model->getDefaultImagePath();
    }

    public static function saveImage($category, $inputName="image")
    {
        $imageName = request()->file($inputName)->getClientOriginalName();
        $imageName = str_replace(' ', '-', $imageName);
        $destinationPath = '/uploads/' . $category . '/' . (string)microtime(true) . Str::random(10);
        $extension = request()->file($inputName)->getClientOriginalExtension();
        request()->file($inputName)->move(public_path() . $destinationPath, $imageName);

        $mainImagePath = public_path() . $destinationPath .'/'. $imageName;
        foreach (self::getImageConfig($category) as $key => $value){
            if ($key != 'ratio' and in_array(strtolower($extension), ['jpg', 'png', 'webp'])){
                $job = new ImageResizer($mainImagePath, $destinationPath, $key, $value["width"], $value["height"]);
                dispatch($job);
            }
        }

        $imageObj = new \stdClass();
        $imageObj->name = $imageName;
        $imageObj->destinationPath = $destinationPath;
        $imageObj->extension = $extension;
        return $imageObj;
    }
}
