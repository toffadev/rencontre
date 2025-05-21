                                                         Documentation reverb

Introduction
Laravel Reverb apporte une communication WebSocket en temps réel ultra-rapide et évolutive directement à votre application Laravel et offre une intégration transparente avec la suite existante d' outils de diffusion d'événements de Laravel .
Installation
Vous pouvez installer Reverb en utilisant la install:broadcastingcommande Artisan :
php artisan install:broadcasting
Configuration
En arrière-plan, la install:broadcastingcommande Artisan exécutera la reverb:installcommande, qui installera Reverb avec un ensemble judicieux d'options de configuration par défaut. Si vous souhaitez modifier la configuration, vous pouvez le faire en mettant à jour les variables d'environnement de Reverb ou en mettant à jour le config/reverb.phpfichier de configuration.
Informations d'identification de la candidature
Pour établir une connexion à Reverb, un ensemble d'identifiants d'application Reverb doit être échangé entre le client et le serveur. Ces identifiants sont configurés sur le serveur et servent à vérifier la requête du client. Vous pouvez définir ces identifiants à l'aide des variables d'environnement suivantes :
REVERB_APP_ID=my-app-id
REVERB_APP_KEY=my-app-key
REVERB_APP_SECRET=my-app-secret

Origines autorisées
Vous pouvez également définir les origines d'où peuvent provenir les requêtes client en modifiant la valeur de allowed_originsconfiguration dans la appssection du config/reverb.phpfichier de configuration. Toute requête provenant d'une origine non répertoriée dans vos origines autorisées sera rejetée. Vous pouvez autoriser toutes les origines en utilisant\* :
'apps' => [
[
'app_id' => 'my-app-id',
'allowed_origins' => ['laravel.com'],
// ...
]
]
Applications supplémentaires
En règle générale, Reverb fournit un serveur WebSocket pour l'application dans laquelle il est installé. Cependant, il est possible de gérer plusieurs applications avec une seule installation Reverb.
Par exemple, vous souhaiterez peut-être gérer une seule application Laravel qui, via Reverb, fournit une connectivité WebSocket à plusieurs applications. Pour ce faire, définissez plusieurs applications appsdans le fichier de configuration de votre applicationconfig/reverb.php :
'apps' => [
[
'app_id' => 'my-app-one',
// ...
],
[
'app_id' => 'my-app-two',
// ...
],
],
SSL
Dans la plupart des cas, les connexions WebSocket sécurisées sont gérées par le serveur Web en amont (Nginx, etc.) avant que la demande ne soit transmise par proxy à votre serveur Reverb.
Cependant, il peut parfois être utile, notamment lors du développement local, que le serveur Reverb gère directement les connexions sécurisées. Si vous utilisez la fonctionnalité de site sécurisé de Laravel Herd ou Laravel Valet et avez exécuté la commande secure sur votre application, vous pouvez utiliser le certificat Herd/Valet généré pour votre site afin de sécuriser vos connexions Reverb. Pour ce faire, définissez la REVERB_HOSTvariable d'environnement sur le nom d'hôte de votre site ou transmettez explicitement l'option hostname au démarrage du serveur Reverb :
php artisan reverb:start --host="0.0.0.0" --port=8080 --hostname="laravel.test"
Étant donné que les domaines Herd et Valet sont résolus en localhost, l'exécution de la commande ci-dessus rendra votre serveur Reverb accessible via le protocole WebSocket sécurisé ( wss) à wss://laravel.test:8080.
Vous pouvez également choisir manuellement un certificat en définissant tlsdes options dans le fichier de configuration de votre application config/reverb.php. Parmi les tlsoptions disponibles, vous pouvez spécifier celles prises en charge par les options contextuelles SSL de PHP :
'options' => [
'tls' => [
'local_cert' => '/path/to/cert.pem'
],
],
Exécution du serveur
Le serveur Reverb peut être démarré à l'aide de la reverb:startcommande Artisan :
php artisan reverb:start
Par défaut, le serveur Reverb sera démarré à 0.0.0.0:8080, le rendant accessible depuis toutes les interfaces réseau.
Si vous devez spécifier un hôte ou un port personnalisé, vous pouvez le faire via les options --hostet --portlors du démarrage du serveur :
php artisan reverb:start --host=127.0.0.1 --port=9000
Vous pouvez également définir REVERB_SERVER_HOSTdes REVERB_SERVER_PORTvariables d'environnement dans le fichier de configuration de votre application .env.
Les variables d'environnement REVERB_SERVER_HOSTet ne doivent pas être confondues avec et . Les premières spécifient l'hôte et le port sur lesquels exécuter le serveur Reverb, tandis que les secondes indiquent à Laravel où envoyer les messages de diffusion. Par exemple, en production, vous pouvez router les requêtes de votre nom d'hôte Reverb public sur le port vers un serveur Reverb fonctionnant sur . Dans ce scénario, vos variables d'environnement seraient définies comme suit :REVERB_SERVER_PORTREVERB_HOSTREVERB_PORT4430.0.0.0:8080
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080

REVERB_HOST=ws.laravel.com
REVERB_PORT=443
Débogage
Pour améliorer les performances, Reverb n'affiche aucune information de débogage par défaut. Si vous souhaitez visualiser le flux de données transitant par votre serveur Reverb, vous pouvez ajouter l' --debugoption à la reverb:startcommande :
php artisan reverb:start –debug
Redémarrage
Étant donné que Reverb est un processus de longue durée, les modifications apportées à votre code ne seront pas reflétées sans redémarrer le serveur via la reverb:restartcommande Artisan.
Cette reverb:restartcommande garantit que toutes les connexions sont correctement interrompues avant l'arrêt du serveur. Si vous utilisez Reverb avec un gestionnaire de processus tel que Supervisor, le serveur sera automatiquement redémarré par ce gestionnaire une fois toutes les connexions interrompues :
php artisan reverb:restart
Surveillance
Reverb peut être surveillé via une intégration avec Laravel Pulse . En activant l'intégration Pulse de Reverb, vous pouvez suivre le nombre de connexions et de messages traités par votre serveur.
Pour activer l'intégration, assurez-vous d'avoir installé Pulse . Ajoutez ensuite les enregistreurs Reverb au config/pulse.phpfichier de configuration de votre application :
use Laravel\Reverb\Pulse\Recorders\ReverbConnections;
use Laravel\Reverb\Pulse\Recorders\ReverbMessages;

'recorders' => [
ReverbConnections::class => [
'sample_rate' => 1,
],

    ReverbMessages::class => [
        'sample_rate' => 1,
    ],

    // ...

],
Ensuite, ajoutez les cartes Pulse pour chaque enregistreur à votre tableau de bord Pulse :
<x-pulse>
<livewire:reverb.connections cols="full" />
<livewire:reverb.messages cols="full" />
...
</x-pulse>
L'activité de connexion est enregistrée par interrogation régulière des mises à jour. Pour garantir un affichage correct de ces informations sur le tableau de bord Pulse, vous devez exécuter le pulse:checkdémon sur votre serveur Reverb. Si vous utilisez Reverb dans une configuration horizontale , vous ne devez exécuter ce démon que sur un seul de vos serveurs.
Serveur Web
Dans la plupart des cas, Reverb s'exécute sur un port non web de votre serveur. Pour acheminer le trafic vers Reverb, vous devez donc configurer un proxy inverse. Si Reverb s'exécute sur l'hôte 0.0.0.0et le port 8080et que votre serveur utilise le serveur web Nginx, un proxy inverse peut être défini pour votre serveur Reverb avec la configuration de site Nginx suivante :
server {
...

    location / {
        proxy_http_version 1.1;
        proxy_set_header Host $http_host;
        proxy_set_header Scheme $scheme;
        proxy_set_header SERVER_PORT $server_port;
        proxy_set_header REMOTE_ADDR $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "Upgrade";

        proxy_pass http://0.0.0.0:8080;
    }

    ...

}
