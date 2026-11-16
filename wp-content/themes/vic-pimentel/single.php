<?php
/**
 * Project detail (Figma: Post Detail). Hero + block content + optional highlights + related.
 */
get_header();

while (have_posts()):
    the_post();

    $post_id = get_the_ID();
    $card = vp_project_card_data($post_id);
    $highlights = get_field('show_highlights') ? array_filter(get_field('highlights') ?: [], fn($h) => !empty($h['image'])) : [];
    $related = vp_related_ids($post_id, 4);
    ?>
    <main class="main-content project">
        <section class="project-hero">
            <div class="page-light" aria-hidden="true">
                <div class="page-light-sticky"><?php component('scene', ['key' => 'light']); ?></div>
            </div>

            <div class="wrapper ember-entrance-group">
                <div class="column-media project-hero-inner">
                    <div class="project-hero-head ember-entrance">
                        <p class="project-meta">
                            <?php if ($card['category']): ?>
                                <span class="project-category"><?= esc_html($card['category']) ?></span>
                                <span class="project-meta-divider" aria-hidden="true"></span>
                            <?php endif ?>
                            <span class="project-date"><?= esc_html(sprintf(__('Posted on %s', 'rear-theme'), get_the_date('M j, Y'))) ?></span>
                        </p>

                        <h1 class="project-title">
                            <?php if (!empty($card['favicon'])): ?>
                                <?php component('image', $card['favicon'], ['max_size' => 'thumbnail', 'html_tag' => 'span', 'class' => 'project-favicon', 'lazyload' => false, 'is_priority_candidate' => false]); ?>
                            <?php endif ?>
                            <span><?= esc_html($card['company']) ?></span>
                        </h1>
                    </div>

                    <div class="project-thumbnail ember-entrance">
                        <?php component('media', $card['media'], [
                            'mode' => 'autoplay',
                            'max_size' => 'large',
                            'sizes_attr' => '(min-width: 1024px) 800px, 90vw',
                            'lazyload' => false,
                            'class' => 'project-thumbnail-media',
                        ]); ?>
                    </div>
                </div>
            </div>
        </section>

        <div class="post-body">
            <?php the_content(); ?>
        </div>

        <?php if ($highlights): ?>
            <section class="project-section project-highlights">
                <div class="wrapper ember-entrance-group">
                    <h2 class="project-section-heading ember-entrance"><?= vp_emphasis(__('Highlights in *This Project*', 'rear-theme')) ?></h2>

                    <ul class="project-highlights-grid ember-entrance">
                        <?php foreach (array_values($highlights) as $index => $highlight):
                            $modal_id = 'highlight-' . $post_id . '-' . $index; ?>
                            <li>
                                <?php component('card-section', [
                                    'title' => $highlight['title'],
                                    'media' => vp_media($highlight['image'], $highlight['video'] ?? null),
                                    'modal_id' => $modal_id,
                                ]); ?>
                            </li>
                        <?php endforeach ?>
                    </ul>
                </div>

                <?php foreach (array_values($highlights) as $index => $highlight): ?>
                    <?php component('media-modal', [
                        'id' => 'highlight-' . $post_id . '-' . $index,
                        'title' => $highlight['title'],
                        'media' => vp_media($highlight['image'], $highlight['video'] ?? null),
                    ]); ?>
                <?php endforeach ?>
            </section>
        <?php endif ?>

        <?php if ($related): ?>
            <section class="project-section project-related">
                <div class="wrapper ember-entrance-group">
                    <div class="project-section-head ember-entrance">
                        <h2 class="project-section-heading"><?= vp_emphasis(__('You May *Also Like*', 'rear-theme')) ?></h2>
                        <?php component('button', [
                            'style' => 'text',
                            'link' => ['title' => __('View All', 'rear-theme'), 'url' => home_url('/')],
                        ]); ?>
                    </div>

                    <ul class="project-related-grid ember-entrance">
                        <?php foreach ($related as $id): ?>
                            <?php component('card-post', ['post_id' => $id, 'html_tag' => 'li']); ?>
                        <?php endforeach ?>
                    </ul>
                </div>
            </section>
        <?php endif ?>
    </main>
<?php
endwhile;

get_footer();
