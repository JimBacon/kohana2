<?php defined('SYSPATH') or die('No direct access allowed.');

$lang = array
(
	'there_can_be_only_one' => 'Na jedno wywołanie strony można powołać tylko jedną instancję Kohany.',
	'uncaught_exception'    => 'Nieobsługiwany %s: %s w pliku %s w lini %s',
	'invalid_method'        => 'Nieprawidłowa metoda <tt>%s</tt> wywołana w <tt>%s</tt>.',
	'cannot_write_log'      => 'Katalog dziennika w konfiguracji wskazuje na położenie tylko do odczytu.',
	'resource_not_found'    => 'Żadany %s, <tt>%s</tt>, Nie może zostać znaleziony.',
	'invalid_filetype'      => 'Żądany typ pliku, <tt>.%s</tt>, w konfiguracji widoków nie jest podany jako dozwolony.',
	'no_default_route'      => 'Proszę ustawić domyślny adres wywołania w <tt>config/routes.php</tt>.',
	'no_controller'         => 'Kohana nie była w stanie określić kontrolera obsługującego wywołanie: %s',
	'page_not_found'        => 'Wywołana strona, <tt>%s</tt>, nie może zostać znaleziona.',
	'stats_footer'          => 'Czas wywołania: {execution_time} sekund, użyto {memory_usage} pamięci. Wygenerowano przez Kohana v{kohana_version}.',
	'error_message'         => 'Wystąpił błąd w <strong>lini %s</strong> z <strong>%s</strong>.',
	'stack_trace'           => 'Zrzut stosu (Stack Trace)',
);