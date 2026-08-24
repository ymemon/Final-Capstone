<?php
/**
 * Plugin Name: AZW FAQ Schema Sitewide
 * Description: FAQPage JSON-LD per service/city page. Added by azwebcorp_aeo_content.py
 */
add_action('wp_head', function () {
    $faqs = array(
        'case-studies' => <<<'FAQJSON'
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [
    {
      "@type": "Question",
      "name": "What kinds of businesses does AZWebCorp work with?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Local Arizona service businesses, e-commerce stores, software companies, and international clients — including managed sites for an ISO 27001-certified IT firm in Ireland and a laboratory software provider."
      }
    },
    {
      "@type": "Question",
      "name": "Can I see examples of your work?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Yes — this page highlights selected projects, and we're happy to walk you through live examples relevant to your industry. Call (480) 818-5761."
      }
    }
  ]
}
FAQJSON,
        'web-design-queen-creek-az' => <<<'FAQJSON'
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [
    {
      "@type": "Question",
      "name": "How much does web design cost in Queen Creek, AZ?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Most Queen Creek small-business websites run $2,000-$10,000 depending on pages and features. AZWebCorp serves Queen Creek from nearby Gilbert with fixed up-front quotes — call (480) 818-5761."
      }
    },
    {
      "@type": "Question",
      "name": "Do you meet with Queen Creek clients in person?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Yes — AZWebCorp is based in Gilbert, Arizona and serves the entire Phoenix East Valley including Queen Creek, with in-person consultations available."
      }
    },
    {
      "@type": "Question",
      "name": "How long does a Queen Creek website take to build?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Standard business sites launch in 2-4 weeks; e-commerce and custom builds in 4-8 weeks, with SEO and mobile optimization included."
      }
    }
  ]
}
FAQJSON,
        'web-design-tempe-az' => <<<'FAQJSON'
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [
    {
      "@type": "Question",
      "name": "How much does web design cost in Tempe, AZ?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Most Tempe small-business websites run $2,000-$10,000 depending on pages and features. AZWebCorp serves Tempe from nearby Gilbert with fixed up-front quotes — call (480) 818-5761."
      }
    },
    {
      "@type": "Question",
      "name": "Do you meet with Tempe clients in person?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Yes — AZWebCorp is based in Gilbert, Arizona and serves the entire Phoenix East Valley including Tempe, with in-person consultations available."
      }
    },
    {
      "@type": "Question",
      "name": "How long does a Tempe website take to build?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Standard business sites launch in 2-4 weeks; e-commerce and custom builds in 4-8 weeks, with SEO and mobile optimization included."
      }
    }
  ]
}
FAQJSON,
        'web-design-scottsdale-az' => <<<'FAQJSON'
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [
    {
      "@type": "Question",
      "name": "How much does web design cost in Scottsdale, AZ?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Most Scottsdale small-business websites run $2,000-$10,000 depending on pages and features. AZWebCorp serves Scottsdale from nearby Gilbert with fixed up-front quotes — call (480) 818-5761."
      }
    },
    {
      "@type": "Question",
      "name": "Do you meet with Scottsdale clients in person?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Yes — AZWebCorp is based in Gilbert, Arizona and serves the entire Phoenix East Valley including Scottsdale, with in-person consultations available."
      }
    },
    {
      "@type": "Question",
      "name": "How long does a Scottsdale website take to build?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Standard business sites launch in 2-4 weeks; e-commerce and custom builds in 4-8 weeks, with SEO and mobile optimization included."
      }
    }
  ]
}
FAQJSON,
        'web-design-chandler-az' => <<<'FAQJSON'
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [
    {
      "@type": "Question",
      "name": "How much does web design cost in Chandler, AZ?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Most Chandler small-business websites run $2,000-$10,000 depending on pages and features. AZWebCorp serves Chandler from nearby Gilbert with fixed up-front quotes — call (480) 818-5761."
      }
    },
    {
      "@type": "Question",
      "name": "Do you meet with Chandler clients in person?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Yes — AZWebCorp is based in Gilbert, Arizona and serves the entire Phoenix East Valley including Chandler, with in-person consultations available."
      }
    },
    {
      "@type": "Question",
      "name": "How long does a Chandler website take to build?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Standard business sites launch in 2-4 weeks; e-commerce and custom builds in 4-8 weeks, with SEO and mobile optimization included."
      }
    }
  ]
}
FAQJSON,
        'web-design-mesa-az' => <<<'FAQJSON'
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [
    {
      "@type": "Question",
      "name": "How much does web design cost in Mesa, AZ?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Most Mesa small-business websites run $2,000-$10,000 depending on pages and features. AZWebCorp serves Mesa from nearby Gilbert with fixed up-front quotes — call (480) 818-5761."
      }
    },
    {
      "@type": "Question",
      "name": "Do you meet with Mesa clients in person?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Yes — AZWebCorp is based in Gilbert, Arizona and serves the entire Phoenix East Valley including Mesa, with in-person consultations available."
      }
    },
    {
      "@type": "Question",
      "name": "How long does a Mesa website take to build?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Standard business sites launch in 2-4 weeks; e-commerce and custom builds in 4-8 weeks, with SEO and mobile optimization included."
      }
    }
  ]
}
FAQJSON,
        'web-design-phoenix-az' => <<<'FAQJSON'
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [
    {
      "@type": "Question",
      "name": "How much does web design cost in Phoenix, AZ?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Most Phoenix small-business websites run $2,000-$10,000 depending on pages and features. AZWebCorp serves Phoenix from nearby Gilbert with fixed up-front quotes — call (480) 818-5761."
      }
    },
    {
      "@type": "Question",
      "name": "Do you meet with Phoenix clients in person?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Yes — AZWebCorp is based in Gilbert, Arizona and serves the entire Phoenix East Valley including Phoenix, with in-person consultations available."
      }
    },
    {
      "@type": "Question",
      "name": "How long does a Phoenix website take to build?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Standard business sites launch in 2-4 weeks; e-commerce and custom builds in 4-8 weeks, with SEO and mobile optimization included."
      }
    }
  ]
}
FAQJSON,
        'arizona-digital-marketing' => <<<'FAQJSON'
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [
    {
      "@type": "Question",
      "name": "What digital marketing services does AZWebCorp offer?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "SEO, web design and development, content marketing, marketing automation, and answer-engine optimization (AEO) for AI search — all from our Gilbert, Arizona office."
      }
    },
    {
      "@type": "Question",
      "name": "How much should an Arizona small business spend on digital marketing?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "A common starting range is $1,000-$3,500 per month combining SEO and content. The right mix depends on your market — we scope it in a free consultation."
      }
    },
    {
      "@type": "Question",
      "name": "What is AEO and do I need it?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Answer Engine Optimization makes your business citable by AI assistants like ChatGPT, Perplexity and Google AI Overviews. As more customers ask AI for recommendations, AEO protects and grows your visibility."
      }
    }
  ]
}
FAQJSON,
        'phoenix-web-development' => <<<'FAQJSON'
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [
    {
      "@type": "Question",
      "name": "How much does web development cost in Phoenix?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Most Phoenix small-business websites run $2,000-$10,000 depending on scope. AZWebCorp quotes fixed prices — call (480) 818-5761."
      }
    },
    {
      "@type": "Question",
      "name": "How long does a Phoenix website take to build?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Standard business sites launch in 2-4 weeks; e-commerce and custom builds in 4-8 weeks."
      }
    }
  ]
}
FAQJSON,
        'arizona-seo-services' => <<<'FAQJSON'
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [
    {
      "@type": "Question",
      "name": "Can you guarantee first-place rankings?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "No ethical SEO provider can guarantee a specific Google ranking. Rankings depend on competition, search intent, location, the user’s device, site quality, search-algorithm changes, and many factors outside any agency’s control. We focus on the work most likely to improve your technical foundation, relevance, visibility, and ability to convert qualified visitors."
      }
    },
    {
      "@type": "Question",
      "name": "How long does SEO take?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "The timeline depends on your website’s condition, market competition, existing authority, service area, content needs, and the amount of work required. Some technical fixes can be completed quickly. Meaningful organic-growth results commonly require consistent work and measurement over several months."
      }
    },
    {
      "@type": "Question",
      "name": "Do you work only with Arizona businesses?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Arizona is our home market, and we work extensively with businesses in Phoenix, Mesa, Gilbert, Tempe, Chandler, Scottsdale, the East Valley, Tucson, and statewide. We also provide SEO and web-development services for businesses across the United States."
      }
    },
    {
      "@type": "Question",
      "name": "Do I need a new website before starting SEO?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Not always. We begin with an audit and determine whether your existing website can be improved effectively or whether a redesign, WordPress rebuild, or custom development project would create a stronger foundation."
      }
    },
    {
      "@type": "Question",
      "name": "Do you perform the implementation work?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Yes. We can provide strategy and recommendations, work with your internal team, or implement technical and website changes directly. Our WordPress and custom web-development experience allows us to address many SEO recommendations in-house."
      }
    }
  ]
}
FAQJSON,
    );
    if (!is_page()) {
        return;
    }
    $slug = get_post_field('post_name');
    if (!isset($faqs[$slug])) {
        return;
    }

    /**
     * Skip any page that already carries its own FAQPage block.
     *
     * These entries were written when the city pages were 45-word stubs with
     * no visible FAQ at all - which was already the wrong side of Google's
     * rule that FAQ markup must reflect content the visitor can see. Now that
     * some of those pages have been given real FAQ sections, emitting this as
     * well puts two FAQPage blocks on one URL, and Google's usual response to
     * that is to trust neither.
     *
     * Checking the stored content rather than maintaining a second list means
     * this stays correct for any page that gets its own FAQ later.
     */
    $post = get_post();
    if ($post && stripos($post->post_content, '"@type": "FAQPage"') !== false) {
        return;
    }
    if ($post && stripos($post->post_content, '"@type":"FAQPage"') !== false) {
        return;
    }

    echo '<script type="application/ld+json">' . $faqs[$slug] . '</script>';
});
