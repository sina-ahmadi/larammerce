<?php

namespace App\Models;

use App\Models\Interfaces\ImageContract;
use App\Models\Interfaces\SeoContract as SeoableContract;
use App\Models\Traits\Seoable;
use App\Utils\CMS\Template\Contents\Audio;
use App\Utils\CMS\Template\Contents\File;
use App\Utils\CMS\Template\Contents\Image;
use App\Utils\CMS\Template\Contents\Link;
use App\Utils\CMS\Template\Contents\RichText;
use App\Utils\CMS\Template\Contents\Text;
use App\Utils\CMS\Template\Contents\Video;
use App\Utils\CMS\Template\ContentTypes;
use App\Utils\CMS\Template\Directives;
use App\Utils\CMS\Template\RelativeBladeType;
use App\Utils\CMS\Template\TemplateService;
use App\Utils\Common\ImageService;
use App\Utils\Common\RequestService;
use App\Utils\Translation\Traits\Translatable;
use DateTime;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Yangqi\Htmldom\Htmldom;

/**
 * @property integer id
 * @property integer directory_id
 * @property string blade_name
 * @property string data
 * @property string image_path
 * @property string seo_description
 * @property string seo_keywords
 * @property string seo_title
 * @property DateTime created_at
 * @property DateTime updated_at
 *
 * @property Directory directory
 * @property Tag[] tags
 *
 * Class WebPage
 * @package App\Models
 */
class WebPage extends BaseModel implements ImageContract, SeoableContract
{
    use Seoable, Translatable;

    protected $table = 'web_pages';
    protected $fillable = [
        'directory_id', 'blade_name', 'data', 'seo_title', 'seo_keywords', 'seo_description',
    ];

    protected static array $SORTABLE_FIELDS = ['id', 'blade_name', 'created_at'];
    protected static array $SEARCHABLE_FIELDS = ['blade_name', 'data'];
    protected static array $TRANSLATABLE_FIELDS = [
        "seo_title" => ["text", "textarea:normal"],
        "seo_keywords" => ["text", "textarea:normal"],
        "seo_description" => ["text", "textarea:normal"],
        "data" => ["text", "custom"]
    ];
    protected static string $TRANSLATION_EDIT_FORM = "admin.pages.web-page.translate";

    private $contents;

    public function __construct(array $attributes = [])
    {
        $this->contents = [];
        parent::__construct($attributes);
    }

    public function getBladeNameAttribute()
    {
        $locale = $this->getDefaultLocale();
        $blade_name = $this->attributes["blade_name"];
        $blade_path = TemplateService::getBladePath($blade_name);
        if ($locale !== null) {
            $tmp_blade_name = $blade_name . ".{$locale}";
            $tmp_blade_path = TemplateService::getBladePath($tmp_blade_name);
            if (!file_exists($tmp_blade_path))
                TemplateService::copyBlade($blade_path, $tmp_blade_path);
            return $tmp_blade_name;
        }
        return $blade_name;
    }

    public function setDataAttribute($data): void
    {
        dd($data);
    }

    /*
     * Relation Methods
     */

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function directory()
    {
        return $this->belongsTo('\\App\\Models\\Directory', 'directory_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphToMany
     */
    public function tags()
    {
        return $this->morphToMany('\\App\\Models\\Tag', 'taggable');
    }


    /*
     * Scope Methods
     */


    /*
     * OverWritten Methods
     */
    public function delete()
    {
        $this->tags()->detach();
        $this->review()->delete();

        return parent::delete();
    }

    public function getTitle()
    {
        return $this->directory->title;
    }

    public function getSeoTitle()
    {
        if ($this->seo_title !== null and strlen($this->seo_title) > 0)
            return $this->seo_title;
        return $this->directory->title;
    }


    /*
    * Image Methods
    */

    public function hasImage()
    {
        return isset($this->image_path);
    }

    public function getImagePath()
    {
        return $this->image_path;
    }

    public function setImagePath()
    {
        $tmpImage = ImageService::saveImage($this->getImageCategoryName());
        $this->image_path = $tmpImage->destinationPath . '/' . $tmpImage->name;
        $this->save();
    }

    public function removeImage()
    {
        $this->image_path = null;
        $this->save();
    }

    public function getDefaultImagePath()
    {
        return '/admin_dashboard/images/No_image.jpg.png';
    }

    public function getImageCategoryName()
    {
        return 'web_page';
    }

    public function save(array $options = [])
    {
        $this->fillData(request());
        return parent::save($options);
    }

    public function fillData(Request $request)
    {
        if (isset($this->id) and $this->id != null) {
            $this->loadContents();
            foreach ($request->all() as $attrName => $attrValue) {
                if (str_contains($attrName, "data__")) {
                    $attrNameParts = explode("__", $attrName);
                    $contentId = $attrNameParts[3];
                    if (key_exists($contentId, $this->contents)) {
                        $attrValueType = RequestService::getType($attrValue);
                        if ($attrValueType === RequestService::FILE_ATTRIBUTE) {
                            $tmpImage = ImageService::saveImage("not_categorized", $attrName);
                            $this->contents[$contentId]->{Str::camel("set_" . $attrNameParts[2])}(
                                $tmpImage->destinationPath . '/' . $tmpImage->name);
                        } else if ($attrValueType === RequestService::TEXT_ATTRIBUTE) {
                            $this->contents[$contentId]->{Str::camel("set_" . $attrNameParts[2])}($attrValue);
                        }
                    }
                }
            }
            $this->data = serialize($this->contents);
            $this->saveBladeContents();
        }
    }


    /*
     * Helper Methods
     */
    /**
     * @return bool
     */
    private function loadContents()
    {
        $result = true;
        if (count(is_countable($this->contents) ? $this->contents : []) == 0) {
            if ($this->data != null and strlen($this->data) > 0) {
                try {
                    $this->contents = unserialize($this->data);
                } catch (Exception $e) {
                    $this->contents = [];
                }
            } else {
                $this->contents = [];
            }
        }
        $this->loadBladeContents();
        return $result;
    }

    private function loadBladeContents()
    {
        $tmpContents = $this->contents;
        $this->contents = [];
        if ($this->blade_name != null and strlen($this->blade_name) > 0) {
            $bladePath = TemplateService::getBladePath($this->blade_name);
            $bladeContent = TemplateService::getBladeContent($bladePath);
            $html = new Htmldom($bladeContent);
            $contentTags = $html->find("[" . Directives::CONTENT . "]");

            foreach (RelativeBladeType::values() as $relativeBladePostfix) {
                $relativeBladePath = TemplateService::getBladePath($this->blade_name . $relativeBladePostfix);
                $relativeBladeContent = TemplateService::getBladeContent($relativeBladePath);
                if ($relativeBladeContent != "") {
                    $relativeHtml = new Htmldom($relativeBladeContent);
                    $relativeContentTags = $relativeHtml->find("[" . Directives::CONTENT . "]");
                    $contentTags = array_merge($contentTags, $relativeContentTags);
                }
            }
            $this->loadContentTags($contentTags, $tmpContents);
        }
    }

    private function loadContentTags($contentTags, $firstContents)
    {
        foreach ($contentTags as $contentTag) {
            $contentType = $contentTag->attr[Directives::CONTENT_TYPE];
            $contentId = $contentTag->attr[Directives::CONTENT];
            $contentTitle = $contentTag->attr[Directives::TITLE];
            //TODO: fix the issue with old content id's with changed content structure.
            if ($contentType != null and strlen($contentType) != 0
                and $contentId != null and strlen($contentId) != 0
                and $contentTitle != null and strlen($contentTitle) != 0) {
                if (!key_exists($contentId, $firstContents)) {
                    if ($contentType === ContentTypes::TEXT) {
                        $content = trim($contentTag->innertext);
                        if ((isset($contentTag->attr[Directives::UNSHARED])
                            and $contentTag->attr[Directives::UNSHARED] == "true"))
                            $content = get_unshared_content($contentId, $this);
                        $newText = new Text($contentId, $contentTitle, $content);
                        $this->contents[$contentId] = $newText;

                    } else if ($contentType === ContentTypes::RICH_TEXT) {
                        $content = trim($contentTag->innertext);
                        if ((isset($contentTag->attr[Directives::UNSHARED])
                            and $contentTag->attr[Directives::UNSHARED] == "true"))
                            $content = get_unshared_content($contentId, $this);
                        $newRichText = new RichText($contentId, $contentTitle, $content);
                        $this->contents[$contentId] = $newRichText;

                    } else if ($contentType === ContentTypes::LINK) {

                        $newLink = new Link($contentId, $contentTitle,
                            trim($contentTag->href), trim($contentTag->innertext));
                        $this->contents[$contentId] = $newLink;

                    } else if ($contentType === ContentTypes::FILE) {

                        $newFile = new File($contentId, $contentTitle, $contentTag->innertext, $contentTag->href);
                        $this->contents[$contentId] = $newFile;

                    } else if ($contentType === ContentTypes::IMAGE) {

                        $newImage = new Image($contentId, $contentTitle, $contentTag->alt, $contentTag->src);
                        $this->contents[$contentId] = $newImage;

                    } else if ($contentType === ContentTypes::AUDIO) {
                        $newAudio = new Audio($contentId, $contentTitle, $contentTag->src, $contentTag->format);
                        $this->contents[$contentId] = $newAudio;

                    } else if ($contentType === ContentTypes::VIDEO) {
                        $newVideo = new Video($contentId, $contentTitle, $contentTag->src, $contentTag->format,
                            $contentTag->poster, $contentTag->controls, $contentTag->auto_play, $contentTag->loop);
                        $this->contents[$contentId] = $newVideo;
                    }
                } else {
                    $this->contents[$contentId] = $firstContents[$contentId];
                }
            }
        }
    }

    private function setContentTags($content_tags)
    {
        foreach ($content_tags as $content_tag) {
            $content_type = $content_tag->attr[Directives::CONTENT_TYPE];
            $content_id = $content_tag->attr[Directives::CONTENT];
            $content_title = $content_tag->attr[Directives::TITLE];
            if ($content_type != null and strlen($content_type) != 0
                and $content_id != null and strlen($content_id) != 0
                and $content_title != null and strlen($content_title) != 0
                and key_exists($content_id, $this->contents)
                and !(isset($content_tag->attr[Directives::UNSHARED])
                    and $content_tag->attr[Directives::UNSHARED] == "true")) {
                if ($content_type === ContentTypes::TEXT) {

                    $content_tag->innertext = $this->contents[$content_id]->getContent();

                } else if ($content_type === ContentTypes::RICH_TEXT) {

                    $content_tag->innertext = $this->contents[$content_id]->getContent();

                } else if ($content_type === ContentTypes::LINK) {

                    $content_tag->href = $this->contents[$content_id]->getHref();
                    $content_tag->innertext = $this->contents[$content_id]->getContent();

                } else if ($content_type === ContentTypes::FILE) {

                    $content_tag->innertext = $this->contents[$content_id]->getName();
                    $content_tag->href = $this->contents[$content_id]->getHref();

                } else if ($content_type === ContentTypes::IMAGE) {

                    $content_tag->alt = $this->contents[$content_id]->getAlt();
                    $content_tag->src = $this->contents[$content_id]->getSrc();

                } else if ($content_type === ContentTypes::AUDIO) {

                    $content_tag->src = $this->contents[$content_id]->getSrc();
                    $content_tag->format = $this->contents[$content_id]->getFormat();

                } else if ($content_type === ContentTypes::VIDEO) {
                    $content_tag->src = $this->contents[$content_id]->getSrc();
                    $content_tag->format = $this->contents[$content_id]->getFormat();
                    $content_tag->poster = $this->contents[$content_id]->getPoster();
                    $content_tag->controls = $this->contents[$content_id]->hasControls();
                    $content_tag->auto_play = $this->contents[$content_id]->isAutoPlay();
                    $content_tag->loop = $this->contents[$content_id]->hasLoop();
                }
            }
        }
    }

    private function saveBladeContents()
    {
        if ($this->blade_name != null and strlen($this->blade_name) > 0) {
            $this->updateBladeContent(TemplateService::getBladePath($this->blade_name));
            foreach (RelativeBladeType::values() as $relative_blade_postfix) {
                $relative_blade_path = TemplateService::getBladePath($this->blade_name . $relative_blade_postfix);
                $this->updateBladeContent($relative_blade_path, $relative_blade_postfix);
            }
        }
    }

    private function updateBladeContent($bladePath, $relativeBladePostfix = null)
    {
        $bladeContent = TemplateService::getBladeContent($bladePath);
        $html = null;
        $contentTags = null;
        if ($bladeContent != "" and strlen($bladeContent) > 0) {
            $html = new Htmldom($bladeContent);
            $contentTags = $html->find("[" . Directives::CONTENT . "]");
        }
        if ($contentTags != null)
            $this->setContentTags($contentTags);
        if ($html != null) {
            $bladeName = $this->blade_name;
            if ($relativeBladePostfix != null)
                $bladeName .= $relativeBladePostfix;
            TemplateService::setBladeContent(TemplateService::getViewPath($bladeName), $html);
        }
    }

    public function getContent($contentName)
    {
        if ($this->loadContents() !== false) {
            return $this->contents[$contentName];
        }
        return false;
    }

    public function getContents()
    {
        if ($this->loadContents() != false) {
            return $this->contents;
        }
        return false;
    }

    /**
     * @return string
     */
    public function getSearchUrl(): string
    {
        return '';
    }

    public function getFrontUrl()
    {
        return $this->directory->getFrontUrl();
    }

    public function getSeoDescription()
    {
        return $this->seo_description;
    }

    public function getSeoKeywords()
    {
        return $this->seo_keywords;
    }


    public function isImageLocal()
    {
        return true;
    }
}
