<?php

function hz_reviewsio_company_schema() {
    $api_url = "https://api.reviews.io/merchant/reviews?store=japanskiexperience.com1&per_page=5&page=1";

    $response = wp_remote_get($api_url);

    if (is_wp_error($response)) {
        return; // bail if API fails
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    $reviews = $data['reviews'];
    if (empty($reviews)) {
        return; // no reviews found
    }

    // Build reviews array
    $reviewsArray = [];
    foreach ($reviews as $review) {
        $reviewsArray[] = [
            "@type" => "Review",
            "author" => [
                "@type" => "Person",
                "name"  => trim(str_replace('&amp;quot;', '', $review['reviewer']['first_name'] . " " . $review['reviewer']['last_name']))
            ],
            "datePublished" => substr($review['date_created'], 0, 10), // YYYY-MM-DD
            "reviewBody" => $review['comments'],
            "reviewRating" => [
                "@type" => "Rating",
                "ratingValue" => (int) $review['rating'],
                "bestRating"  => 5
            ],
            "publisher" => [
                "@type" => "Organization",
                "name"  => "Reviews.io"
            ]
        ];
    }

    // Build schema
    $schema = [
      "@context" => "https://schema.org",
        "@type"    => "Organization",
        "name"     => "Japan Ski Experience",
        "url"      => "https://japanskiexperience.com/",
        "logo"     => "https://japanskiexperience.com/wp-content/uploads/2024/04/JapanSki_logo_Normal_BLK_TXT_72dpi.png",
        "sameAs"   => [
            "https://www.facebook.com/JapanSkiExperience",
            "https://www.instagram.com/japanskiexperience/",
            "https://www.youtube.com/@OfficialJapanSkiExperience",
            "https://www.linkedin.com/company/japan-ski-experience/",
            "https://jp.pinterest.com/japan_ski/",
            "https://japanskiexperience.com/",
            "https://www.reviews.io/company-reviews/store/japanskiexperience-com1",
        ],
        "contactPoint" => [
            "@type"            => "ContactPoint",
            "telephone"        => "+81-136-55-6077",
            "email"            => "info@japanskiexperience.com",
            "contactType"      => "Sales",
            "areaServed"       => "Worldwide",
            "availableLanguage"=> "English"
        ],
        "aggregateRating" => [
            "@type"       => "AggregateRating",
            "ratingValue" => $data['stats']['average_rating'],
            "bestRating"  => 5,
            "ratingCount" => $data['stats']['total_reviews']
        ],
        "review" => $reviewsArray,
    ];

    return '<script type="application/ld+json" data-object="StructuredDataOrganization" data-test="schema-by-hamza">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
}