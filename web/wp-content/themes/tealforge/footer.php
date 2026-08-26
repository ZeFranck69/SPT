<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

$context = tealforge_get_context();
?>
        </main>

        <?php tealforge_render('partials/footer.twig', $context); ?>

        <?php wp_footer(); ?>
    </body>
</html>
