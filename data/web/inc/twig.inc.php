<?php

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

$loader = new FilesystemLoader($_SERVER['DOCUMENT_ROOT'].'/templates');
$twig = new Environment($loader, [
  'debug' => $DEV_MODE,
  'cache' => $_SERVER['DOCUMENT_ROOT'].'/templates/cache',
]);

// functions
$twig->addFunction(new TwigFunction('query_string', function (array $params = []) {
  return http_build_query(array_merge($_GET, $params));
}));

$twig->addFunction(new TwigFunction('is_uri', function (string $uri, string $where = null) {
  if (is_null($where)) $where = $_SERVER['REQUEST_URI'];
  return preg_match('/'.$uri.'/i', $where);
}));

// filters
$twig->addFilter(new TwigFilter('rot13', 'str_rot13'));
$twig->addFilter(new TwigFilter('base64_encode', 'base64_encode'));
$twig->addFilter(new TwigFilter('formatBytes', 'formatBytes'));