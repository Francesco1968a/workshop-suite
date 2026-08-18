<?php

if (!defined('ABSPATH')) exit;

/**
 * Handles Internationalization (i18n), localization dictionaries, 
 * script translations, and Polylang / WPML multi-language integration.
 */
final class WS_I18n implements WS_Module {

    public function should_load(): bool {
        return true;
    }

    public function register(): void {
        add_action('init', [$this, 'load_textdomain']);
        add_action('init', [$this, 'register_multilingual_strings'], 20);
    }

    public function load_textdomain(): void {
        load_plugin_textdomain(
            'workshop-suite',
            false,
            dirname(plugin_basename(WS_PATH . 'workshop-suite.php')) . '/languages'
        );
    }

    /**
     * Get current active language code (e.g. 'it', 'en', 'es', 'fr', 'de').
     */
    public static function get_current_language(): string {
        if (function_exists('pll_current_language')) {
            $pll = pll_current_language('slug');
            if (!empty($pll)) return strtolower($pll);
        }
        if (defined('ICL_LANGUAGE_CODE') && ICL_LANGUAGE_CODE) {
            return strtolower(ICL_LANGUAGE_CODE);
        }
        $locale = is_admin() ? get_user_locale() : get_locale();
        return strtolower(substr($locale, 0, 2));
    }

    /**
     * Frontend & UI Translation Dictionary
     */
    public static function get_dictionary(string $lang = ''): array {
        if (empty($lang)) {
            $lang = self::get_current_language();
        }

        $translations = [
            'it' => [
                'name'               => 'Nome',
                'surname'            => 'Cognome',
                'fullname'           => 'Nome e Cognome',
                'email'              => 'Indirizzo Email',
                'phone'              => 'Telefono / WhatsApp',
                'city'               => 'Città di provenienza',
                'notes'              => 'Note o richieste particolari',
                'privacy_accept'     => 'Accetto l\'informativa sulla privacy e il trattamento dei dati personali',
                'submit_btn'         => 'Invia Iscrizione',
                'sending'            => 'Invio in corso...',
                'success_title'      => 'Iscrizione Inviata!',
                'success_msg'        => 'Grazie per la tua candidatura. Ti abbiamo inviato una mail di conferma con tutti i dettagli.',
                'error_required'     => 'Compila tutti i campi obbligatori contrassegnati con *',
                'error_email'        => 'Inserisci un indirizzo email valido.',
                'error_privacy'      => 'È necessario accettare l\'informativa privacy per procedere.',
                'error_generic'      => 'Si è verificato un errore durante l\'invio. Riprova più tardi.',
                'sold_out'           => 'Posti Esauriti',
                'seats_left'         => 'posti disponibili',
                'last_seat'          => 'Ultimo posto disponibile!',
                'open_reg'           => 'Iscrizioni Aperte',
                'price'              => 'Quota di partecipazione',
                'deposit'            => 'Anticipo richiesto',
                'balance'            => 'Saldo da versare',
                'dates'              => 'Periodo e Date',
                'location'           => 'Luogo di ritrovo',
                'trainer'            => 'Docente / Trainer',
                'spoken_languages'   => 'Lingue parlate',
                'official_website'   => 'Sito Ufficiale',
                'whatsapp_contact'   => 'Contatta su WhatsApp',
                'add_to_calendar'    => 'Aggiungi al Calendario',
                'calendar_google'    => 'Google Calendar',
                'calendar_apple'     => 'Apple / iCal',
                'upcoming_workshops' => 'Prossimi Workshop in Programma',
                'no_events'          => 'Nessun evento in programma al momento.',
            ],
            'en' => [
                'name'               => 'First Name',
                'surname'            => 'Last Name',
                'fullname'           => 'Full Name',
                'email'              => 'Email Address',
                'phone'              => 'Phone / WhatsApp',
                'city'               => 'City / Country',
                'notes'              => 'Notes or special requests',
                'privacy_accept'     => 'I accept the privacy policy and consent to personal data processing',
                'submit_btn'         => 'Submit Registration',
                'sending'            => 'Submitting...',
                'success_title'      => 'Registration Submitted!',
                'success_msg'        => 'Thank you for signing up! We have sent a confirmation email with all details.',
                'error_required'     => 'Please fill in all required fields marked with *',
                'error_email'        => 'Please enter a valid email address.',
                'error_privacy'      => 'You must accept the privacy policy to proceed.',
                'error_generic'      => 'An error occurred during submission. Please try again.',
                'sold_out'           => 'Sold Out',
                'seats_left'         => 'seats available',
                'last_seat'          => 'Last seat available!',
                'open_reg'           => 'Registration Open',
                'price'              => 'Participation Fee',
                'deposit'            => 'Required Deposit',
                'balance'            => 'Remaining Balance',
                'dates'              => 'Dates & Period',
                'location'           => 'Meeting Point / Location',
                'trainer'            => 'Trainer / Instructor',
                'spoken_languages'   => 'Spoken Languages',
                'official_website'   => 'Official Website',
                'whatsapp_contact'   => 'Chat on WhatsApp',
                'add_to_calendar'    => 'Add to Calendar',
                'calendar_google'    => 'Google Calendar',
                'calendar_apple'     => 'Apple / iCal',
                'upcoming_workshops' => 'Upcoming Workshops & Masterclasses',
                'no_events'          => 'No scheduled events at this time.',
            ],
            'es' => [
                'name'               => 'Nombre',
                'surname'            => 'Apellidos',
                'fullname'           => 'Nombre Completo',
                'email'              => 'Correo Electrónico',
                'phone'              => 'Teléfono / WhatsApp',
                'city'               => 'Ciudad / País',
                'notes'              => 'Notas o peticiones especiales',
                'privacy_accept'     => 'Acepto la política de privacidad y el tratamiento de datos personales',
                'submit_btn'         => 'Enviar Inscripción',
                'sending'            => 'Enviando...',
                'success_title'      => '¡Inscripción Enviada!',
                'success_msg'        => '¡Gracias por inscribirte! Te hemos enviado un email de confirmación con todos los detalles.',
                'error_required'     => 'Por favor completa todos los campos obligatorios marcados con *',
                'error_email'        => 'Introduce un correo electrónico válido.',
                'error_privacy'      => 'Debes aceptar la política de privacidad para continuar.',
                'error_generic'      => 'Ha ocurrido un error al enviar. Por favor inténtalo de nuevo.',
                'sold_out'           => 'Plazas Agotadas',
                'seats_left'         => 'plazas disponibles',
                'last_seat'          => '¡Última plaza disponible!',
                'open_reg'           => 'Inscripciones Abiertas',
                'price'              => 'Precio de participación',
                'deposit'            => 'Depósito requerido',
                'balance'            => 'Saldo restante',
                'dates'              => 'Fechas y Período',
                'location'           => 'Lugar de encuentro',
                'trainer'            => 'Profesor / Instructor',
                'spoken_languages'   => 'Idiomas hablados',
                'official_website'   => 'Sitio Web Oficial',
                'whatsapp_contact'   => 'Contactar por WhatsApp',
                'add_to_calendar'    => 'Añadir al Calendario',
                'calendar_google'    => 'Google Calendar',
                'calendar_apple'     => 'Apple / iCal',
                'upcoming_workshops' => 'Próximos Talleres y Cursos',
                'no_events'          => 'No hay eventos programados en este momento.',
            ],
            'fr' => [
                'name'               => 'Prénom',
                'surname'            => 'Nom',
                'fullname'           => 'Nom et Prénom',
                'email'              => 'Adresse Email',
                'phone'              => 'Téléphone / WhatsApp',
                'city'               => 'Ville / Pays',
                'notes'              => 'Notes ou demandes particulières',
                'privacy_accept'     => 'J\'accepte la politique de confidentialité et le traitement de mes données',
                'submit_btn'         => 'Envoyer l\'inscription',
                'sending'            => 'Envoi en cours...',
                'success_title'      => 'Inscription envoyée !',
                'success_msg'        => 'Merci pour votre inscription ! Un email de confirmation vous a été envoyé.',
                'error_required'     => 'Veuillez remplir tous les champs obligatoires marqués d\'un *',
                'error_email'        => 'Veuillez saisir une adresse email valide.',
                'error_privacy'      => 'Vous devez accepter la politique de confidentialité.',
                'error_generic'      => 'Une erreur est survenue lors de l\'envoi. Veuillez réessayer.',
                'sold_out'           => 'Complet',
                'seats_left'         => 'places disponibles',
                'last_seat'          => 'Dernière place disponible !',
                'open_reg'           => 'Inscriptions ouvertes',
                'price'              => 'Tarif de participation',
                'deposit'            => 'Acompte requis',
                'balance'            => 'Solde restant',
                'dates'              => 'Dates et Période',
                'location'           => 'Lieu de rendez-vous',
                'trainer'            => 'Formateur / Enseignant',
                'spoken_languages'   => 'Langues parlées',
                'official_website'   => 'Site Officiel',
                'whatsapp_contact'   => 'Contacter sur WhatsApp',
                'add_to_calendar'    => 'Ajouter au Calendrier',
                'calendar_google'    => 'Google Calendar',
                'calendar_apple'     => 'Apple / iCal',
                'upcoming_workshops' => 'Prochains Ateliers & Formations',
                'no_events'          => 'Aucun événement programmé pour le moment.',
            ],
            'de' => [
                'name'               => 'Vorname',
                'surname'            => 'Nachname',
                'fullname'           => 'Vollständiger Name',
                'email'              => 'E-Mail-Adresse',
                'phone'              => 'Telefon / WhatsApp',
                'city'               => 'Stadt / Land',
                'notes'              => 'Notizen oder besondere Wünsche',
                'privacy_accept'     => 'Ich akzeptiere die Datenschutzerklärung und stimme der Datenverarbeitung zu',
                'submit_btn'         => 'Anmeldung absenden',
                'sending'            => 'Wird gesendet...',
                'success_title'      => 'Anmeldung erfolgreich!',
                'success_msg'        => 'Vielen Dank für Ihre Anmeldung! Wir haben Ihnen eine Bestätigungs-E-Mail gesendet.',
                'error_required'     => 'Bitte füllen Sie alle mit * gekennzeichneten Pflichtfelder aus',
                'error_email'        => 'Bitte geben Sie eine gültige E-Mail-Adresse ein.',
                'error_privacy'      => 'Sie müssen der Datenschutzerklärung zustimmen, um fortzufahren.',
                'error_generic'      => 'Beim Absenden ist ein Fehler aufgetreten. Bitte versuchen Sie es erneut.',
                'sold_out'           => 'Ausgebucht',
                'seats_left'         => 'Plätze verfügbar',
                'last_seat'          => 'Letzter verfügbarer Platz!',
                'open_reg'           => 'Anmeldungen geöffnet',
                'price'              => 'Teilnahmegebühr',
                'deposit'            => 'Erforderliche Anzahlung',
                'balance'            => 'Restbetrag',
                'dates'              => 'Zeitraum & Daten',
                'location'           => 'Treffpunkt / Ort',
                'trainer'            => 'Dozent / Kursleiter',
                'spoken_languages'   => 'Gesprochene Sprachen',
                'official_website'   => 'Offizielle Website',
                'whatsapp_contact'   => 'Auf WhatsApp kontaktieren',
                'add_to_calendar'    => 'Zum Kalender hinzufügen',
                'calendar_google'    => 'Google Calendar',
                'calendar_apple'     => 'Apple / iCal',
                'upcoming_workshops' => 'Kommende Workshops & Kurse',
                'no_events'          => 'Derzeit sind keine Veranstaltungen geplant.',
            ],
        ];

        return $translations[$lang] ?? $translations['en'];
    }

    /**
     * Localize scripts with i18n dictionary
     */
    public static function localize(string $handle): void {
        $lang = self::get_current_language();
        $dict = self::get_dictionary($lang);

        wp_localize_script($handle, 'WS_I18N', [
            'lang' => $lang,
            'dict' => $dict,
        ]);
    }

    /**
     * Register dynamic plugin strings for WPML / Polylang translation
     */
    public function register_multilingual_strings(): void {
        $settings = WS_Settings::get_all();

        $strings_to_register = [
            'site_brand_name'    => $settings['site_brand_name'] ?? '',
            'default_notice'     => $settings['default_notice'] ?? '',
            'proponente_nome'    => $settings['proponente_nome'] ?? '',
            'proponente_ruolo'   => $settings['proponente_ruolo'] ?? '',
            'proponente_bio'     => $settings['proponente_bio'] ?? '',
            'proponente_citta'   => $settings['proponente_citta'] ?? '',
        ];

        foreach ($strings_to_register as $key => $value) {
            if (empty($value)) continue;

            // Polylang string registration
            if (function_exists('pll_register_string')) {
                pll_register_string($key, $value, 'Workshop Suite');
            }

            // WPML string registration
            if (function_exists('icl_register_string')) {
                icl_register_string('Workshop Suite', $key, $value);
            }
        }
    }
}
