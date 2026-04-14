# MQTTTEST
Riceve dai topic MQTT configurati.\
Pubblica in base ai comandi i tempate configurati in "template".

Questo programma consente di forgiare richeste MQTT alle applicazioni e di ottenere le risposte.\
Diversamente da MQTTX tutto il processo di lettura e costruzione dei messaggi è stato automatizzato.

## Parametri a riga di comando:
```
MQTTTEST <template> <parametri>
```
Tutti i parametri iniziano con "-" o "--" e sono nella modalità:
```--chiave valore```

Mettendo i parametri a riga di comando tra doppie parentesi tonde sarà eseguita
l'espansione delle srtringhe:
```
--chiave "((config.UCL.nome))"
```

Per il controller ```index-new.php``` si possono sempre impostare i
parametri del gateway con:

```--devId <device>``` e ```--type <tipo>```

Questi due parametri sono utilizzati come da codice:
```
Dispositivo su cui fare la modifica: <device> 
TYPE: <type>
```
Tutti i parametri finiscono nell'array "par" e sono richiamabili nei template
come ```${par.chiave}``` per impostare un valore di default: ```${par.chiave|default}```
Questa notazione è usata sempre all'interno di stringhe, poi saranno convertite in
numeri se è is_numeric il valore.

Per leggere le guide dei template:
```
MQTTEST --help <template>
```
Per vedere i template:
```
MQTTTEST --list
```
## Altri parametri:
```
 --no-expect    Ignora expect: nel template.
 --dump         Visualizza il dump della richiesta senza inviarla.    
```
## Struttura dei template:
I template sono strutturati così:

Dentro alla directory "template" c'è un file .txt col template e .hlp con la guida.

Una riga del template è il topic.\
Il resto è JSON.
Se la prima riga inizia con ```expect:``` quello sarà il topic da ricevere come risposta.

Al topic ed al JSON è applicata la funzione di espansione delle variabili e funzioni.

Il contesto è questo:
```
[
    'config'    => <configurazione>
    'par'       => <parametri riga di comando>
    'var'       => <sezione var>
    'template'  => <template corrente>
    'topic'     => <lista dei topic>
    'expect'    => <topic atteso>,
    'expectId'  => <idMessaggio atteso>,
    'request'   =>  [
        'time'          =>  time(),
        'timeMillis'    =>  time() * 1000,
        'UUID'          =>  Functions::UUID(),
        'id'            =>  Functions::getRequestId()
    ]
]
```
### L'espansione delle stringhe:
Tramite una funzione di parsing iniziano sempre con ```${``` e finiscono con ```}```.
L'annidamento non è consentito, ma tramite una sisntasi particolare è possibile (vedi seguito).

La stringa contenuta nei tag di espansione è elaborata così:

```${percorso|░░░░░░░░}```

(La seconda parte ```░░░░░░░░``` è opaca per comprendere meglio).

Quindi nella parte "percorso" i parametri sono separati dal punto.
Ad esempio: 
```
${request.time}
```
La parte dopo il carattere "|" è elaborata come alternativa di default se il parametro è mancante o vuoto.

Ad esempio se metti: 
```
${par.foo|Default value}
```
Otterrai:
```
"Default value"
```
Ma se la riga di comando avrà:
```
--foo bar
```
Allora otterrai:
```
"bar"
```

Per comodità sono state inserite due regole interessanti sul primo carattere della seconda parte:
* Se inizia con ```|``` allora, tutta la parte sucessiva sarà eseguita come un'altra espansione di stringa annidata.
* Se inizia con ```@``` allora, sarà invocato un metodo statico della classe ```Fuctions``` dove una ulteriore suddivisione sarà impiegata come parametro del metodo.

Questo permette una sintassi simile a una operazione or:

Template multipli: 
```
${sezione.valore||sezione.valore2|valore di default}
```

Ricordo che se usi l'espansione ```${@UUID}```
dove ```UUID``` è un nome di funzione, sarà invocato 
un metodo statico della classe Functions.

Ad esempio:
```
${@UUID}
```
Crea una UUID casuale.

## Configurazioni dinamiche:
L'espansione delle variabili è applicata anche ai nomi dei topic nei template.

Nel file di configurazione c'è una sezione opzionale ```var``` che è
soggetta all'espansione delle variabili. Può essere usata per evitare
di doversi ripetere.

Tutto il file di configurazione è soggetto all'espansione delle variabili fino ad 
un massimo di 512 cicli.

## Uso:
* Lanciare.
* Attendere la risposta.

Se il template inizia con il comando ```expect:``` allora il programma 
resterà in attesa
sul topic stabilito da ```expect:``` di un messaggio con lo stesso campo
```MessageId``` al livello di payload/applicazione.

Nei template il comando ```expect:``` è inserito per primo, consentendo così 
una semplicità del codice del programma. Nel template però sembrerà che 
l'ordine degli eventi sia diverso. 

Consideriamo il comando ```expect:``` come annotazione/attributo.

## ClientId e RequestId:

Come condizione di default, sono generati dal programma in base a path, 
inode e sistema operativo.

Quindi salvo casi particolari, ogni copia del programma ha una sua```ClientId```.

Le RequestId sono salvate nella subdirectory ```var``` come semplici file 
contatori, contenenti il numero delle richieste.

## Per utenti windows:
Usare l'encoding UTF-8 sulla shell.

Aggiungere php nel path, quindi usare 
```
php MQTTTEST
```
Se usi XAMPP puoi mettere nel path php:
```
set path=%PATH%;c:\xampp\php
```
