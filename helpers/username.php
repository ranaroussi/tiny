<?php

declare(strict_types=1);


class UsernameGenerator {
    /**
     * Generate a username from a full name.
     *
     * @param string $full_name The full name to generate the username from.
     * @param int $max_len The maximum length of the generated username.
     * @return string The generated username.
     */
    public function generate(string $full_name, int $max_len = 8): string {
        $username_parts = array_filter(explode(" ", strtolower($full_name))); //explode and lowercase name
        $username_parts = array_slice($username_parts, 0, 2); //return only first two arry part

        $part1 = (!empty($username_parts[0]))?substr($username_parts[0], 0,8):""; //cut first name to 8 letters
        $part2 = (!empty($username_parts[1]))?substr($username_parts[1], 0,5):""; //cut second name to 5 letters
        $part3 = rand(0, 99);

        $username = substr($part1. $part2, 0, ($max_len - 2)). $part3; //str_shuffle to randomly shuffle all characters
        return $username;
    }
}


tiny::registerHelper('username', function() {
    return new UsernameGenerator();
});
