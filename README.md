### Waau Mendeley Plugin

#### Releases

Le  [releases](../../releases) nel repo github sono incrementali e pronte per essere pubblicate su qualsiasi stanza di wordpress.
Non contengono le dipendenze di dev.


#### Backend

Una volta installato il plugin va poi configurato attraverso la sua settings page dove dovete inserire i dati di un applicazione registrata su dev.mendeley con le credenziali di [florence].

Una volta inserite le credenziali come speificato nel pannello di admin, dovete premere il bottone di richiesta di un access token.
Una volta ottenuto correttamente il token (scadrà dopo un ora), potrete caricare i gruppi e selezionare il gruppo INNOTIO

Salvate.

A questo punto potete premere il bottone  INDEX ALL PUBLICATIONS IN GROUP.

Questa azione fa una catena di richieste all'api di mendeley scaricando tutti i metadati delle pubblicazioni (100 alla volta) li inserisce in una tabella del db creata appositamente. Questo ci permetterà di ricercare FULL text su tutti i campi delle pubblicazioni.

A questo puntosi può passare al front.


#### Frontend

Questo plugin funziona semplicemente inserendo uno shortcode in una qualsiasi pagina.

[mendeleysearch] [qui](../master/public/WaauMendeleyPlugin.php#L452)

Lo short inserisce un form con un campo di ricerca sensibile al evento keyup che lancia le richieste via ajax a un servizio esposto dal plugin

Il servizio ricerca nei campi indicizzati precedentemente e ritorna i risultati in json.

il codice html del front end si trova nel file [gui.html](../master/includes/gui.html) con dei templates di tipo underscore 



viene iniettato anche un [js](../master/public/assets/js/dist/app.js) che dipende da [underscorejs](http://underscorejs.org/) e jquery 

viene iniettato anche un [css](../master/public/assets/css/dist/app.css) (compilato da less)

#### Referenze

* [referenza templates underscore](http://2ality.com/2012/06/underscore-templates.html)
* [mendeley api documents](https://dev.mendeley.com/methods/#retrieving-documents)
* [mendeley api groups](https://dev.mendeley.com/methods/#retrieving-groups)
* [collab mendeley plugin](https://github.com/collab-uniba/wp-mendeleyplugin)
* [mendeley](https://www.mendeley.com/)

