<?php
Component->register('Img', function (...$props) {
    $src = tiny::getStaticURL('img/'. $props['src']);
    return '<img border="0" src="'. $src .'" alt="'. $props['alt'] .'" width="'. $props['width'] .'" height="'. $props['height'] .'">';
});
