<?php

class OfferShop
{
    public function __construct()
    {
        add_shortcode('offer_shop', [$this, 'displayOfferShop']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueStyles']);
    }

    public function displayOfferShop(): string
    {
        global $post;
        if (!$post) {
            return '';
        }

        $offer_id     = $post->ID;
        $offer_fields = get_fields($offer_id);

        $shop_object  = $offer_fields['shop'] ?? null;
        if (!is_object($shop_object)) {
            return '';
        }

        $shop_id     = $shop_object->ID;
        
        $shop_logo   = get_field('logo', $shop_id);
        if (!empty($shop_logo['id'])) {
            $shop_logo_html = wp_get_attachment_image($shop_logo['id'], 'full');
        } else {
            $shop_logo_html = '';
        }

        $shop_name   = esc_html(get_the_title($shop_id));
        $offer_title = esc_html(get_the_title($offer_id));

        $offerShop  = "<div class='offerShop'>\n";
        $offerShop .= "  <div class='offerShop__image'>{$shop_logo_html}</div>\n";
        $offerShop .= "  <div class='offerShop__content'>\n";
        $offerShop .= "    <div class='offerShop__name'>{$shop_name}</div>\n";
        $offerShop .= "    <div class='offerShop__offer'>{$offer_title}</div>\n";
        $offerShop .= "  </div>\n";
        $offerShop .= "</div>";

        return $offerShop;
    }

    public function enqueueStyles(): void
    {
        wp_enqueue_style(
            'mall-settings',
            plugin_dir_url(dirname(__FILE__)) . 'css/offerShop.css',
            []
        );
    }
}