<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

/**
 * Class Article
 *
 * @property int $id
 * @property int $app_id
 * @property int $nid
 * @property int $cate_id
 * @property string $title
 * @property string $sub_title
 * @property string $image
 * @property string $url
 * @property string $label
 * @property string $keyword
 * @property string $code
 * @property string $remark
 * @property int $is_hot
 * @property int $is_recommend
 * @property int $status
 * @property int $type
 * @property int $create_time
 * @property int $update_time
 *
 * @package App\Models
 */
class Article extends Model
{
    protected $table = 'article';

    const CREATED_AT = 'create_time';

    const UPDATED_AT = 'update_time';

    const TypeArticle = 1;

    const TypeAIDialogue = 2;

    const TypeAICreation = 3;

    const TypeAIPicture = 4;

    const TypeSingleCourse = 5;

    const TypeCollectionType = 6;

    protected $casts = [
        'app_id' => 'int',
        'nid' => 'int',
        'cate_id' => 'int',
        'is_hot' => 'int',
        'is_recommend' => 'int',
        'status' => 'int',
        'type' => 'int',
        'images' => 'array',
    ];

    protected $fillable = [
        'app_id',
        'nid',
        'cate_id',
        'title',
        'sub_title',
        'image',
        'images',
        'url',
        'label',
        'keyword',
        'code',
        'duration',
        'collections',
        'source',
        'score',
        'views',
        'likes',
        'comments',
        'column',
        'remark',
        'is_hot',
        'is_recommend',
        'status',
        'sort',
        'type',
        'create_time',
        'update_time',
    ];

    public static function typeMap()
    {
        return [
            self::TypeArticle => '富文本',
            self::TypeAIDialogue => 'AI对话',
            self::TypeAICreation => 'AI生成',
            self::TypeAIPicture => 'AI绘画',
            self::TypeSingleCourse => '单课程',
            self::TypeCollectionType => '合集课程',
        ];
    }

    public static function SourceMap()
    {
        return [
            'bilibili' => 'B站',
            'tiktok' => '抖音',
            'littleRedBook' => '小红书',
            'original' => '原创',
        ];
    }

    public function cate(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ContentCategory::class, 'cate_id', 'id')->select(['id', 'title', 'intro', 'image', 'column']);
    }

    public function content(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ArticleContent::class, 'nid', 'id');
    }

    public function ai_dialogue(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ArticleAiDialogue::class, 'nid', 'id');
    }

    public function ai_creation(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ArticleAiCreation::class, 'nid', 'id');
    }

    public function course(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ArticleCourse::class, 'nid', 'id');
    }

    public static function authors(): array
    {
        return [
            "浅若夏沫",
            "月光柠檬",
            "森屿暖树",
            "竹篱小黄花",
            "徒步旅行的猫",
            "空大萌妹",
            "智商已欠费",
            "幼儿园抢饭第一名",
            "野猪佩奇",
            "辣条总统",
            "亡菁",
            "无瑾",
            "剑惊风雨",
            "夜心",
            "浮生若梦",
            "柠夏微凉",
            "青夜书",
            "一梦江南",
            "森旅迷鹿",
            "浅嫣婉语",
            "会飞的鱼",
            "吃货女神",
            "懒癌晚期",
            "单身·宅男",
            "暴躁网友在线",
            "弑魂力士",
            "巅峰瞬神",
            "剑走偏锋",
            "暗香初醒",
            "狂灵在世",
            "夜影千雪",
            "风见幽香",
            "樱井星野",
            "浅仓璃音",
            "紫藤雪奈",
            "白泽悠月",
            "冰川凛音",
            "神乐绯夜",
            "秋山澪羽",
            "藤原夜樱",
            "黑羽千寻",
            "月影柚子",
            "流萤花火",
            "结城明夜",
            "远坂铃音",
            "七濑花恋",
            "夏目夕颜",
            "苍井初音",
            "霜月华莲",
            "樱坂夜羽",
            "朝雾玲奈",
            "千岛枫花",
            "时雨纱织",
            "雪村绫音",
            "藤崎悠璃",
            "皋月遥香",
        ];
    }

    public static function getOneAuthorName()
    {
        $names = self::authors();

        return $names[array_rand($names)];
    }
}
