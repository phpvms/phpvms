<?php

namespace App\Notifications\Channels\Discord;

use Carbon\Carbon;

/**
 * Original is from https://gist.github.com/freekmurze/e4415090f650e070d3de8b905875cf78
 *
 * Markdown guide: https://birdie0.github.io/discord-webhooks-guide/other/discord_markdown.html
 */
class DiscordMessage
{
    const COLOR_SUCCESS = '0B6623';

    const COLOR_WARNING = 'FD6A02';

    const COLOR_ERROR = 'ED2939';

    public $webhook_url;

    protected $title;

    protected $url;

    protected $thumbnail = [];

    protected $image = [];

    protected $description;

    protected $timestamp;

    protected $footer;

    protected $color;

    protected $author = [];

    protected $fields = [];

    // Supply the webhook URL that this should be going to
    public function webhook(string $url): self
    {
        $this->webhook_url = $url;

        return $this;
    }

    // Title of the embed
    public function title(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    // URL of the Title
    public function url(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    // Thumbnail image (placed right side of embed)
    // ['url' => '']
    public function thumbnail(array $thumbnail): self
    {
        $this->thumbnail = $thumbnail;

        return $this;
    }

    // Main image (placed bottom of embed)
    // ['url' => '']
    public function image(array $image): self
    {
        $this->image = $image;

        return $this;
    }

    /**
     * @param array|string $descriptionLines
     */
    public function description($descriptionLines): self
    {
        if (!is_array($descriptionLines)) {
            $descriptionLines = [$descriptionLines];
        }

        $this->description = implode(PHP_EOL, $descriptionLines);

        return $this;
    }

    // Author details
    // ['name' => '', 'url' => '', 'icon_url' => '']
    public function author(array $author): self
    {
        $this->author = $author;

        return $this;
    }

    // Fields
    public function fields(array $fields): self
    {
        $this->fields = [];
        foreach ($fields as $name => $value) {
            $this->fields[] = [
                'name'   => '**'.$name.'**', // bold
                'value'  => $value,
                'inline' => true,
            ];
        }

        return $this;
    }

    public function footer(string $footer): self
    {
        $this->footer = $footer;

        return $this;
    }

    // Fixed Success Color
    public function success(): self
    {
        $this->color = hexdec('0B6623'); // static::COLOR_SUCCESS;

        return $this;
    }

    // Fixed Warning
    public function warning(): self
    {
        $this->color = hexdec('FD6A02'); // static::COLOR_WARNING;

        return $this;
    }

    // Fixed Error
    public function error(): self
    {
        $this->color = hexdec('ED2939'); // static::COLOR_ERROR;

        return $this;
    }

    // Custom Color
    public function color(string $embed_color): self
    {
        $this->color = hexdec($embed_color);

        return $this;
    }

    public function toArray(): array
    {
        $embeds = [
            'type'        => 'rich',
            'color'       => $this->color,
            'title'       => $this->title,
            'url'         => $this->url,
            'thumbnail'   => $this->thumbnail,
            'description' => $this->description,
            'author'      => $this->author,
            'image'       => $this->image,
            'timestamp'   => Carbon::now('UTC'),
        ];

        if (empty($embeds['thumbnail'])) {
            unset($embeds['thumbnail']);
        }

        if (empty($embeds['image'])) {
            unset($embeds['image']);
        }

        if (!empty($this->fields)) {
            $embeds['fields'] = $this->fields;
        }

        if (!empty($this->footer)) {
            $embeds['footer'] = [
                'text' => $this->footer,
            ];
        }

        return [
            'embeds' => [$embeds],
        ];
    }
}
