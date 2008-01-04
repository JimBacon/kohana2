<?php defined('SYSPATH') or die('No direct access allowed.');

$lang = array
(
	// Class errors
	'error_format'  => 'Twój komunikat błędu musi zawierać łańcuch {message} .',
	'invalid_rule'  => 'Użyto niepoprawnej reguły walidacji: %s',

	// General errors
	'unknown_error' => 'Nieznany błąd walidacji podczas walidowania pola %s.',
	'required'      => 'Pole %s jest wymagane.',
	'min_length'    => 'Minimalna wymagana ilość znaków dla pola %s to %d.',
	'max_length'    => 'Maksymalana wymagana ilość znaków dla pola %s to %d.',
	'exact_length'  => 'Wymagana ilość znaków dla pola %s to dokładnie %d.',
	'in_array'      => 'Wartość pola %s musi zostać wybrana z listy.',
	'matches'       => 'Pole %s musi być identyczne z polem %s.',
	'valid_url'     => 'Pole %s musi zawierać poprawny adres URL, zaczynający się od %s://.',
	'valid_email'   => 'Pole %s musi zawierać poprawny adres email.',
	'valid_ip'      => 'Pole %s musi zawierać poprawny numer IP.',
	'valid_type'    => 'Pole %s może zawierać wyłącznie znaki typu %s.',
	'range'         => 'Pole %s musi się zawierać w określonym zakresie.',
	'regex'         => 'Pole %s nie odpowiada zdefiniowanej masce wprowadzania.',
	'depends_on'    => 'Pole %s jest zależne od pola %s.',

	// Upload errors
	'user_aborted'  => 'Przerwano podczas wysyłania pliku %s.',
	'invalid_type'  => 'Plik %s ma nieprawidłowy typ.',
	'max_size'      => 'Rozmiar pliku %s przekracza dozwoloną wartość. Maksymalna wielkość to %s.',
	'max_width'     => 'Szerokość pliku %s przekracza dozwoloną wartość. Maksymalna szerokość to %spx.',
	'max_height'    => 'Wysokość pliku %s przekracza dozwoloną wartość. Maksymalna wysokość to %spx.',

        // Field types
        'alpha'         => 'litera',
        'alpha_dash'    => 'litera, podkreślenie i myślnik',
        'digit'         => 'cyfra',
        'numeric'       => 'liczba',

);