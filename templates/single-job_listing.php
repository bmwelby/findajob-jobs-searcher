<?php
if (!defined('ABSPATH')) exit;

get_header();

while (have_posts()) : the_post();
  $pid = get_the_ID();

  $company  = get_post_meta($pid, '_fjs_company', true);
  $location = get_post_meta($pid, '_fjs_location', true);
  $postcode = get_post_meta($pid, '_fjs_postcode', true);
  $salary   = get_post_meta($pid, '_fjs_salary', true);
  $posted   = get_post_meta($pid, '_fjs_posted', true);
  $closing  = get_post_meta($pid, '_fjs_closing', true);
  $cty      = get_post_meta($pid, '_fjs_contract_type', true);
  $cti      = get_post_meta($pid, '_fjs_contract_time', true);
  $apply_url = get_post_meta($pid, '_fjs_url', true);

  // New description fields (preferred)
  $desc_html = get_post_meta($pid, '_fjs_description_html', true);
  $desc_text = get_post_meta($pid, '_fjs_description_text', true);

  $expired = get_post_meta($pid, '_fjs_expired', true) === '1';

  $similar = FJS_API::get_similar_jobs($pid, 5);

  // Choose what to render
  $description_to_render = '';
  if (is_string($desc_html) && trim($desc_html) !== '') {
    $description_to_render = $desc_html;
  } else {
    // Fall back to post_content (which we also set during upsert),
    // and if that’s empty, fall back to text.
    $content = get_the_content();
    if (is_string($content) && trim($content) !== '') {
      $description_to_render = apply_filters('the_content', $content);
    } elseif (is_string($desc_text) && trim($desc_text) !== '') {
      $description_to_render = wpautop(esc_html($desc_text));
    }
  }
?>
<main class="fjs-single">
  <article <?php post_class('fjs-single__article'); ?>>
    <header class="fjs-single__header">
      <h1 class="fjs-single__title"><?php the_title(); ?></h1>

      <?php if ($company): ?>
        <p class="fjs-single__company"><strong><?php echo esc_html($company); ?></strong></p>
      <?php endif; ?>

      <p class="fjs-single__meta">
        <?php if ($location) echo esc_html($location); ?>
        <?php if ($postcode) echo ($location ? ' • ' : '') . esc_html($postcode); ?>
      </p>

      <?php if ($cty || $cti): ?>
        <p class="fjs-single__meta"><?php echo esc_html(trim($cty . ($cti ? ' • ' . $cti : ''))); ?></p>
      <?php endif; ?>

      <?php if ($salary): ?>
        <p class="fjs-single__meta"><?php echo esc_html($salary); ?></p>
      <?php endif; ?>

      <?php if ($posted): ?>
        <p class="fjs-single__meta"><em>Posted: <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($posted))); ?></em></p>
      <?php endif; ?>

      <?php if ($closing): ?>
        <p class="fjs-single__meta"><em>Closes: <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($closing))); ?></em></p>
      <?php endif; ?>

      <?php if ($expired): ?>
        <p class="fjs-single__expired"><strong>This job may have expired.</strong></p>
      <?php endif; ?>

      <?php if ($apply_url): ?>
        <p class="fjs-single__apply">
          <a class="fjs-button" href="<?php echo esc_url($apply_url); ?>" target="_blank" rel="nofollow noopener">Apply on Find a job</a>
        </p>
      <?php endif; ?>
    </header>

    <section class="fjs-single__description">
      <?php
        // $desc_html is already sanitised via wp_kses in the upsert pipeline.
        // We still run it through wp_kses_post as a final belt-and-braces.
        echo $description_to_render ? wp_kses_post($description_to_render) : '';
      ?>
    </section>

    <?php if (!empty($similar)): ?>
      <aside class="fjs-similar" aria-label="Similar jobs">
        <h2 class="fjs-similar__title">Similar jobs</h2>
        <ul class="fjs-similar__list">
          <?php foreach ($similar as $sj): ?>
            <li class="fjs-similar__item">
              <a class="fjs-similar__link" href="<?php echo esc_url($sj['local_permalink'] ?? '#'); ?>">
                <?php echo esc_html($sj['title'] ?? 'Untitled'); ?>
              </a>
              <?php if (!empty($sj['company'])): ?>
                <span class="fjs-similar__meta"> — <?php echo esc_html($sj['company']); ?></span>
              <?php endif; ?>
              <?php if (!empty($sj['location']) || !empty($sj['postcode'])): ?>
                <span class="fjs-similar__meta"> (<?php echo esc_html(($sj['location'] ?? '') ?: ($sj['postcode'] ?? '')); ?>)</span>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
        <p class="fjs-similar__note">Results come from Find a job and may change.</p>
      </aside>
    <?php endif; ?>

  </article>
</main>
<?php
endwhile;

get_footer();
