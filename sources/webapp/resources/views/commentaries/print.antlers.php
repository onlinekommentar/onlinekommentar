<header class="header">
    <p class="header-label">
        {{ trans:commentary_on }}
    </p>
    <h1 class="header-title">
        {{ title }}
    </h1>
    <p class="header-authors">
        {{ trans:commentary_by }} {{ assigned_authors | pluck('name') | join(', ') }}<br>
        {{ trans:edited_by }} {{ assigned_editors | pluck('name') | join(', ') }}
    </p>
</header>
<section class="citation">
    <p class="citation-label">
        {{ trans:suggested_citation }}
    </p>
    <p class="citation-text">
        {{ suggested_citation_long }}
    </p>
    <p class="citation-text">
        {{ trans:short_citation }}: {{ suggested_citation_short }}
    </p>
</section>
<section class="legal-text">
    {{ legal_text }}
</section>
<section class="toc">
    toc
</section>
<main class="content">
    {{ content }}
</main>