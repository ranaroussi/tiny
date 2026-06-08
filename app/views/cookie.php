<?php
tiny::layout()->default(
    title: 'Cookie policy',
    alternate: tiny::router()->permalink . '.md',
);
?>

<h1><?php echo tiny::data()->page->header; ?></h1>
<?php echo tiny::data()->page->article; ?>


<?php tiny::layout()->default('/'); ?>
