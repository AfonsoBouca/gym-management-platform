<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

$join_href = isset($_SESSION['user_id']) ? '/profile.php?tab=membership' : '/register.php';

$pdo = get_db();
$stmt = $pdo->query("SELECT id, name, price, description, features FROM membership_plans WHERE is_active = 1 ORDER BY price ASC");
$membership_plans = $stmt->fetchAll();

$page_title       = 'Fitness Down — Built in Silence';
$page_description = 'Fitness Down — a gym built for those who train in silence. Premium equipment, no compromises.';

include __DIR__ . '/includes/header.php';
?>

<main>

    <section class="hero" aria-labelledby="hero-title">

        <div class="hero__media" aria-hidden="true">
            <div class="hero__panel">
                <video class="hero__video" autoplay muted loop playsinline>
                    <source src="/assets/videos/gym_take1.mp4" type="video/mp4">
                </video>
            </div>
            <div class="hero__panel">
                <video class="hero__video" autoplay muted loop playsinline>
                    <source src="/assets/videos/gym_take2.mp4" type="video/mp4">
                </video>
            </div>
            <div class="hero__overlay"></div>
        </div>

        <div class="hero__content">
            <p class="hero__subtitle">Built in Silence</p>
            <h1 id="hero-title" class="hero__brand-name">FITNESS DOWN</h1>
            <div class="hero__actions">
                <?php if (!isset($_SESSION['user_id'])): ?>
                <a href="register.php" class="button button--primary button--lg">Join Now</a>
                <?php endif; ?>
            </div>
        </div>

        <a href="#stats" class="hero__scroll" aria-label="Scroll to next section">
            <span aria-hidden="true">↓</span>
        </a>
    </section>

    <section id="stats" class="stats" aria-label="The facility in numbers">
        <div class="ticker">
            <p class="ticker__track">
                <span>Open 24h Daily &nbsp;&nbsp;|&nbsp;&nbsp;
                     1500m² of Premium Workspace &nbsp;&nbsp;
                     |&nbsp;&nbsp; Mechanical Excellence &nbsp;&nbsp;|
                     &nbsp;&nbsp; Est. 2026  </span>
            </p>
        </div>
    </section>

    <section class="highlights" aria-labelledby="highlights-title">
        <header class="section-header">
            <p class="eyebrow">What defines us</p>
            <h2 id="highlights-title" class="section-header__title">The space</h2>
        </header>

        <div class="highlights__grid">
            <article class="highlight">
                <figure class="highlight__media">
                    <img src="/assets/images/iron_floor.png" alt="Heavy steel dumbbells in a raw, clean free weights area" class="highlight__image" loading="lazy">
                </figure>
                <div class="highlight__body">
                    <h3 class="highlight__title">The Iron Floor</h3>
                    <p class="highlight__text">
                        The absolute foundation. Heavy steel dumbbells, metal plates, and raw space devoted entirely to serious free weight training.
                    </p>
                </div>
            </article>

            <article class="highlight">
                <figure class="highlight__media">
                    <img src="/assets/images/silence.png" alt="Perfectly aligned rows of black squat racks" class="highlight__image" loading="lazy">
                </figure>
                <div class="highlight__body">
                    <h3 class="highlight__title">Power Racks</h3>
                    <p class="highlight__text">
                        Perfectly aligned rows of black squat racks in a dark, quiet environment. Pure focus and isolation for heavy compound lifts.
                    </p>
                </div>
            </article>

            <article class="highlight">
                <figure class="highlight__media">
                    <img src="/assets/images/identity.png" alt="Sleek black modern plate-loaded machines under dramatic spotlights" class="highlight__image" loading="lazy">
                </figure>
                <div class="highlight__body">
                    <h3 class="highlight__title">The Fleet</h3>
                    <p class="highlight__text">
                        A wide corridor of pristine, modern plate-loaded machines under dramatic spotlights. Hand-picked for perfect biomechanics.
                    </p>
                </div>
            </article>

            <article class="highlight">
                <figure class="highlight__media">
                    <img src="/assets/images/recovery_pool.png" alt="Sleek dark indoor lap pool" class="highlight__image" loading="lazy">
                </figure>
                <div class="highlight__body">
                    <h3 class="highlight__title">Recovery Pool</h3>
                    <p class="highlight__text">
                        A sleek, black-tiled indoor lap pool focused entirely on athletic recovery. Cold, dark water under dramatic and moody lighting.
                    </p>
                </div>
            </article>
        </div>
    </section>

    <section id="memberships" class="memberships" aria-labelledby="memberships-title">
        <header class="section-header">
            <p class="eyebrow">Choose your access</p>
            <h2 id="memberships-title" class="section-header__title">Memberships</h2>
        </header>

        <ul class="plan-list">
            <?php 
            $plan_classes = ['plan--featured', 'plan--lilac', 'plan--gold'];
            $plan_badges = ['Most chosen', 'Best offer', 'Premium'];
            foreach ($membership_plans as $idx => $plan): 
                $class = $plan_classes[$idx % 3];
                $badge = $plan_badges[$idx % 3];
                if (strcasecmp($plan['name'], 'Essential') === 0) {
                    $class = 'plan--featured';
                    $badge = 'Most chosen';
                } elseif (strcasecmp($plan['name'], 'All Access') === 0) {
                    $class = 'plan--lilac';
                    $badge = 'Best offer';
                } elseif (strcasecmp($plan['name'], 'Elite') === 0) {
                    $class = 'plan--gold';
                    $badge = 'Premium';
                }
                $features = array_filter(array_map('trim', explode('|', $plan['features'])));
            ?>
            <li>
                <article class="plan <?= e($class) ?>">
                    <?php if ($badge): ?>
                        <p class="plan__badge"><?= e($badge) ?></p>
                    <?php endif; ?>
                    <header class="plan__header">
                        <h3 class="plan__name"><?= e($plan['name']) ?></h3>
                        <p class="plan__price">
                            <span class="plan__currency">€</span><span class="plan__amount"><?= (int)$plan['price'] ?></span>
                            <span class="plan__period">/ month</span>
                        </p>
                    </header>
                    <?php if (!empty($plan['description'])): ?>
                        <p class="plan__summary">
                            <?= e($plan['description']) ?>
                        </p>
                    <?php endif; ?>
                    <ul class="plan__features">
                        <?php foreach ($features as $feat): ?>
                            <li><?= e($feat) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <a href="<?= $join_href ?>" class="button button--ghost button--block">Select <?= e($plan['name']) ?></a>
                </article>
            </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="promo-banner" aria-label="Promoção especial">
        <p class="promo-banner__text">
            Until May 20 — <span class="promo-banner__highlight">50% off</span> your first 3 months
        </p>
    </section>

    <section class="callout">
        <h3 class="callout__text">FITNESS DOWN? &nbsp; BUILD IT UP NOW!</h3>
    </section>

    <section class="location" aria-labelledby="location-title">
        <div class="location__map">
            <iframe
                src="https://maps.google.com/maps?q=Rua+Dr.+Roberto+Frias,+Porto&t=&z=15&ie=UTF8&iwloc=&output=embed"
                title="Fitness Down location">
            </iframe>
        </div>

        <div class="location__info">
            <div class="location__block">
                <h2 id="location-title" class="location__heading">Location</h2>
                <address class="location__address">
                    Rua Dr. Roberto Frias 400<br>
                    4200-465 Porto<br>
                    Portugal
                </address>
            </div>
        </div>
    </section>

</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
