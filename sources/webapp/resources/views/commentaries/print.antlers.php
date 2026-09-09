<header class="header">
    <span class="running-title">{{ title }}</span>
    <span class="running-authors">{{ assigned_authors | pluck('name') | join(' / ') }}</span>
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

<section class="status-of-processing">
    <p>{{ trans:status_of_processing }} {{ last_modified format="d.m.Y" }}</p>
    <p>{{ trans:generated_on }} {{ generation_date }}</p>
    {{ if doi }}
        <p>DOI: {{ doi }}</p>
    {{ /if }}
</section>

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

<section class="entry-toc">
    <p class="header-label">
        {{ trans:table_of_contents }}
    </p>
    {{ toc }}
</section>

<main class="content">
    {{ content }}
</main>
