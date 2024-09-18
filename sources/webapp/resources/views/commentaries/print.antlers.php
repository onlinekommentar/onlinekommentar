<header class="header">
    <img src="{{ config:app:url }}/img/ok-logo-text_{{ locale }}.svg" class="header-logo">
    {{ if original_language:handle && original_language:handle !== locale }}
        <div class="header-translation">
            {{ trans:is_translated original_language="{ trans :key="original_language:handle" }" }}
        </div>
    {{ /if }}
    <p class="header-label">
        {{ trans:commentary_on }}
    </p>
    <h1 class="header-title">
        {{ title }}
    </h1>
    <p class="header-authors">
        {{ trans:commentary_by }} {{ assigned_authors | pluck('name') | join(' / ') }}<br>
        {{ trans:edited_by }} {{ assigned_editors | pluck('name') | join(' / ') }}
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
    <p class="toc-label">
        {{ trans:table_of_contents }}
    </p>
    <div class="toc-list">
        {{ toc }}
    </div>
</section>
<main class="content">
    {{ content }}
</main>