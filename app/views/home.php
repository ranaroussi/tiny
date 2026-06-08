<?php Component->require(['Img']); ?>
<?php Layout->default(title: 'Home'); ?>

<h1><?php echo tiny::data()->greeting; ?></h1>
<?php Component->Img(src: 'php-logo.png', alt: 'Tiny: PHP Framework', width: 128, height: 128); ?>

<?php Layout->default(); ?>
