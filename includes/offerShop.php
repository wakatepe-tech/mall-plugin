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
        $offer_fields = get_fields($post->ID);
        $shop_object  = $offer_fields['shop'] ?? null;

        if (is_object($shop_object)) {
            $shop_id     = $shop_object->ID;
            $shop_name   = get_the_title($shop_id);
            $shop_logo   = get_field('logo', $shop_id);
            $offer_title = get_the_title($post->ID);

            $offerShop  = "<div class='offerShop'>";
            $offerShop .= "<div class='offerShop__image'>";
            $offerShop .= $shop_logo ? wp_get_attachment_image($shop_logo['ID'], 'full') : '';
            $offerShop .= "</div>";
            $offerShop .= "<div class='offerShop__content'>";
            $offerShop .= "<div class='offerShop__name'><p>" . esc_html($shop_name) . "</p></div>";
            $offerShop .= "<div class='offerShop__offer'><p>" . esc_html($offer_title) . "</p></div>";
            $offerShop .= "</div>";
            $offerShop .= "</div>";

            return $offerShop;
        }

        return '';
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