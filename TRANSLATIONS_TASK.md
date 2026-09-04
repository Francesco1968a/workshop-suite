# WSMaker — Task: aggiungere 4 nuove lingue (es_ES, fr_FR, de_DE, pt_BR)

## Contesto

WSMaker è un plugin WordPress (repo `Francesco1968a/wsmaker`) con sorgente in **inglese**.
Esiste già una traduzione italiana completa e funzionante (`it_IT`), usata come riferimento
strutturale per questo lavoro. Il plugin ha **due sistemi di traduzione separati e indipendenti**
che vanno entrambi tradotti per ogni nuova lingua — non sono la stessa cosa.

**Non serve toccare nessun file `.php` o `.vue`/`.js` del plugin.** Questo è un lavoro di sola
traduzione: si creano nuovi file di traduzione, seguendo esattamente lo stesso schema dei file
italiani già presenti.

## Lingue da aggiungere

| Codice locale | Lingua |
|---|---|
| `es_ES` | Spagnolo |
| `fr_FR` | Francese |
| `de_DE` | Tedesco |
| `pt_BR` | Portoghese (Brasile) |

## Parte 1 — Traduzioni PHP (menu, pagine admin, messaggi lato server)

- Sorgente inglese: `languages/wsmaker.pot` (226 stringhe, formato gettext standard).
- Riferimento italiano già fatto: `languages/wsmaker-it_IT.po` (e il suo compilato `.mo`).
- Per ogni lingua, creare `languages/wsmaker-<locale>.po` partendo da una copia di `wsmaker.pot`,
  compilando ogni `msgstr ""` con una traduzione naturale (non letterale parola-per-parola —
  l'italiano fatto in questa sessione è l'esempio di qualità da seguire).
- Attenzione a `%s` / `%d` (placeholder `sprintf`) e a eventuali tag HTML dentro le stringhe:
  vanno preservati identici, si traduce solo il testo intorno.
- **Non lasciare mai `msgstr` identico al `msgid` inglese** a meno che la parola sia davvero
  uguale in entrambe le lingue (es. "Dashboard", "Email", "Stripe", "WSMaker" — nomi propri/tecnici).
  Questo è successo per errore nella prima versione italiana ed è stato un bug reale da correggere
  a posteriori: 27 voci di menu erano rimaste in inglese perché marcate "tradotte" con lo stesso
  testo del sorgente.
- Dopo aver compilato ogni `.po`, generare il `.mo` corrispondente:
  ```bash
  msgfmt -o languages/wsmaker-<locale>.mo languages/wsmaker-<locale>.po
  ```
  (richiede `gettext`, es. `brew install gettext` su macOS se `msgfmt` non è già disponibile).

## Parte 2 — Traduzioni Vue/JS (i pannelli admin veri e propri)

- Riferimento: `includes/i18n/it_IT.php` — 470 chiavi, un array PHP associativo `'chiave' => 'traduzione'`.
- Per ogni lingua, creare `includes/i18n/<locale>.php` con la stessa identica lista di chiavi,
  stesso ordine possibile ma non obbligatorio, valori tradotti.
- Le chiavi in inglese (il fallback, cioè cosa deve diventare la traduzione) si trovano
  direttamente nel codice sorgente Vue/JS come secondo argomento di ogni chiamata `t('chiave', 'testo inglese')`:
  - 15 file in `admin-src/src/**/*.vue`
  - 1 file `assets/dist/locandine.js` (vanilla JS, non passa da Vite — è editabile direttamente,
    ma per questo task non va toccato, serve solo a leggere i fallback inglesi delle chiavi `loc_*`)
- Il modo più veloce per estrarre l'elenco completo chiave→fallback inglese aggiornato:
  ```bash
  grep -rhoE "t\('[a-zA-Z0-9_]+', *('[^']*'|\"[^\"]*\")" admin-src/src assets/dist/locandine.js | sort -u
  ```
- Non serve nessuna ricompilazione Vite: questi file PHP vengono letti a runtime da
  `class-ws-admin-settings-page.php::vue_i18n_map()` in base al locale WordPress corrente.
- Se una chiave manca nella nuova lingua, il fallback inglese scatta automaticamente — meglio
  lasciare una chiave non tradotta (fallback inglese) che tradurla in modo sbagliato o forzato.

## Cosa NON tradurre (regola fissa del progetto)

Solo il "chrome" dell'interfaccia (etichette, pulsanti, messaggi di sistema). Mai il contenuto
inserito dall'utente: nomi di categorie/eventi reali, testi descrittivi delle locandine, dati
dei partecipanti. Questi non fanno parte del lavoro di traduzione.

## Verifica finale (per ogni lingua)

1. `php -l` su ogni nuovo file `.php` creato (sintassi).
2. `msgfmt -c` sul `.po` prima di compilare (o lasciare che fallisca rumorosamente se malformato).
3. Confermare che nel `.po` non restino `msgstr` identici al `msgid` inglese, salvo i pochi
   nomi propri elencati sopra (usare uno script tipo quello in Parte 1 per il controllo).
4. Nessun test visivo necessario in questa fase — la verifica visiva su ambiente reale
   (WordPress Playground o lr-test) la farà chi merge il lavoro.

## Consegna

Aprire una pull request sul repo `Francesco1968a/wsmaker` con:
- 4 nuovi file `languages/wsmaker-<locale>.po` + `.mo` (8 file totali)
- 4 nuovi file `includes/i18n/<locale>.php`

Nessuna modifica ad altri file del plugin è prevista o necessaria per questo task.
