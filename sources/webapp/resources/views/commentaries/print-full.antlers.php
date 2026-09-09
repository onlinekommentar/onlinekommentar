<div class="volume-page">
    <h1 class="header-title">{{ legal_domain_title }}</h1>
    {{ if last_change_date }}
        <p class="volume-page-info">{{ trans:last_change }} {{ last_change_date }}</p>
    {{ /if }}
    {{ if total_volumes > 1 }}
        <p class="volume-page-info">{{ trans:volume }} {{ volume_number }} / {{ total_volumes }}</p>
    {{ /if }}
    <p class="volume-page-info">{{ trans:generated_on }} {{ generation_date }}</p>
    <img src="{{ config:app:url }}/img/ok-logo-text_{{ locale }}.svg" class="header-logo">
</div>

<nav class="entries-toc">
    <p class="header-label">{{ trans:full_table_of_contents }}</p>
    {{ toc_html }}
</nav>

{{ entries }}

    <header class="header" id="entry-{{ id }}">
        <span class="running-title">{{ title }}</span>
        <span class="running-authors">{{ assigned_authors | pluck('name') | join(' / ') }}</span>
        {{ if original_language:handle && original_language:handle !== locale }}
            <div class="header-translation">
                {{ trans:is_translated original_language="{ trans :key="original_language:handle" }" }}
            </div>
        {{ /if }}
        <p class="header-label">{{ trans:commentary_on }}</p>
        <h2 class="header-title">{{ title }}</h2>
        <p class="header-authors">
            {{ trans:commentary_by }} {{ assigned_authors | pluck('name') | join(' / ') }}
        </p>
    </header>

    {{ if suggested_citation_long || suggested_citation_short }}
        <section class="citation">
            {{ if suggested_citation_long }}
                <p class="citation-label">
                    {{ trans:suggested_citation }}
                </p>
                <p class="citation-text">
                    {{ suggested_citation_long }}
                </p>
            {{ /if }}
            {{ if suggested_citation_short }}
                <p class="citation-text">
                    {{ trans:short_citation }}: {{ suggested_citation_short }}
                </p>
            {{ /if }}
        </section>
    {{ /if }}

    {{ if legal_text }}
        <section class="legal-text">
            {{ legal_text }}
        </section>
    {{ /if }}

    {{ if toc }}
        <section class="entry-toc">
            <p class="header-label">
                {{ trans:table_of_contents }}
            </p>
            {{ toc }}
        </section>
    {{ /if }}

    <div class="section content">
        {{ rendered_content }}
    </div>

{{ /entries }}
