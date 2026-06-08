<?php
class Home
{
    use TinyController;

    public function get($request, $response)
    {
        tiny::data()->greeting = 'Hello, Tiny!';
        $response->render(); // default template is home.php
    }
}
