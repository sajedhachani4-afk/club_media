=====================================================================
 CLUB MEDIA — ISET GAFSA  |  Create. Capture. Inspire.
 Guide d'installation (XAMPP / Windows)        دليل التركيب
=====================================================================

Contenu :
  PARTIE 1 — Français
  PARTIE 2 — بالدارجة التونسية (arabe tunisien simple)
  PARTIE 3 — Informations techniques (sécurité, hébergement)

Prérequis : XAMPP avec PHP 8.0 ou plus, Apache et MySQL/MariaDB.
Le site n'a besoin ni de Node.js, ni de Composer, ni de npm.

Important : les bibliothèques (GSAP, ScrollTrigger, AOS, Swiper, Lucide,
QRCode.js, jsQR) et les polices Google sont chargées depuis des CDN
(cdnjs, jsDelivr, unpkg, Google Fonts). Une connexion Internet est donc
nécessaire pour avoir toutes les animations, les QR codes et le scanner.
Sans Internet, le site reste utilisable (pages, réservations, admin),
mais sans animations, sans QR code affiché et sans scanner caméra
(la saisie manuelle du code du ticket fonctionne toujours).


=====================================================================
 PARTIE 1 — FRANÇAIS
=====================================================================

1) Télécharger et extraire le ZIP
   - Téléchargez club-media.zip.
   - Clic droit > « Extraire tout… ». Vous obtenez un dossier « club-media ».

2) Copier le projet dans XAMPP
   - Copiez le dossier « club-media » dans  C:\xampp\htdocs\
   - Résultat attendu :  C:\xampp\htdocs\club-media\index.php
   - Vérifiez qu'il n'y a pas de dossier en double
     (pas de  club-media\club-media\ ).

3) Démarrer Apache et MySQL
   - Ouvrez « XAMPP Control Panel ».
   - Cliquez sur « Start » en face de Apache, puis en face de MySQL.
   - Les deux lignes doivent devenir vertes.

4) Ouvrir phpMyAdmin
   - Dans le navigateur : http://localhost/phpmyadmin

5) Importer database.sql
   - Cliquez sur l'onglet « Importer ».
   - « Choisir un fichier » : sélectionnez  club-media\database.sql
   - Cliquez sur « Importer » (en bas). Le script crée tout seul la base
     « club_media_db », les tables et 3 événements d'exemple.
   - Vous pouvez relancer l'import sans rien perdre (il ne duplique pas).

6) Configurer la connexion à la base de données
   - Ouvrez  club-media\config\database.php  avec le Bloc-notes.
   - XAMPP standard : ne changez rien
       DB_HOST = localhost   DB_PORT = 3306
       DB_NAME = club_media_db
       DB_USER = root        DB_PASS = '' (vide)
   - Si votre MySQL utilise un autre port (ex. 3307), un autre mot de
     passe ou un autre utilisateur, modifiez ces valeurs. C'est le seul
     fichier de connexion du projet.
   - Pour utiliser un autre nom de base, remplacez « club_media_db »
     aussi dans database.sql (2 endroits : CREATE DATABASE et USE).

7) Créer l'administrateur (une seule fois)
   - Ouvrez  http://localhost/club-media/setup.php
   - Vérifiez que toutes les lignes sont en vert (✔).
   - Choisissez identifiant, e-mail et mot de passe
     (10 caractères minimum, au moins une lettre et un chiffre).
   - Il n'existe AUCUN compte par défaut. Dès qu'un administrateur est créé,
     setup.php se verrouille. Sur un vrai hébergement, supprimez ensuite
     le fichier setup.php.

8) Ouvrir le site
   - Page d'accueil :  http://localhost/club-media/

9) Ouvrir l'administration
   - http://localhost/club-media/admin.php
   - Connectez-vous avec le compte créé à l'étape 7.

10) Ajouter le premier événement
   - Menu « Événements » > « Nouvel événement ».
   - Renseignez titre, description, catégorie, dates et heures, lieu,
     type de billet (gratuit / payant), prix en TND, capacité, statut.
   - Ajoutez une image de couverture (JPG, PNG ou WebP), cochez « Publié »,
     puis enregistrez. Les 3 événements d'exemple peuvent être modifiés ou
     supprimés.

11) Envoyer la première vidéo
   - Menu « Vidéos » > ajouter.
   - Source « Fichier » : choisissez un MP4 / WebM (max. 200 Mo par défaut),
     ou source « YouTube » : collez le lien de la vidéo.
   - Choisissez l'événement associé, cochez « Publié », enregistrez.
   - Si l'envoi d'un gros fichier échoue : dans  C:\xampp\php\php.ini
     augmentez  upload_max_filesize  et  post_max_size  (ex. 210M et 220M),
     puis redémarrez Apache.
   - Pour une vidéo de fond sur l'accueil : envoyez d'abord la vidéo (fichier),
     puis Paramètres > « vidéo d'accueil » ; sinon l'image d'accueil
     (Paramètres) ou l'image de secours est utilisée.

12) Créer et vérifier un ticket de test
   a) Côté public : ouvrez un événement > « Réserver », remplissez nom,
      e-mail, téléphone et quantité, validez.
      - Événement GRATUIT : le ticket est valide tout de suite, avec QR code.
      - Événement PAYANT : statut « En attente de paiement ». Aucun paiement
        en ligne n'existe : le ticket n'est valide qu'après confirmation
        manuelle par un administrateur.
   b) Paiement confirmé (billet payant) : Administration > « Réservations »
      > « Confirmer le paiement ».
   c) Vérification : Administration > « Tickets » (scanner). Autorisez la
      caméra (le navigateur exige HTTPS ou localhost) et présentez le QR code,
      ou collez le code du ticket à la main.
      Résultats possibles : Valide / Déjà utilisé / Annulé / Non confirmé /
      Invalide. Un ticket valide est marqué « utilisé » : il ne peut plus
      servir une deuxième fois.
   d) Le visiteur retrouve son ticket dans « Mes réservations »
      avec son e-mail ET son code de réservation (format R-XXXXXXXX).

13) Dépannage
   - Erreur 404 « Not Found » :
       * Le dossier doit être exactement  C:\xampp\htdocs\club-media
       * L'adresse doit être  http://localhost/club-media/  (pas /htdocs/).
       * Apache doit être démarré (vert) dans XAMPP.
   - « Impossible de se connecter à la base » / page d'aide d'installation :
       * MySQL est-il démarré (vert) ?
       * Avez-vous importé database.sql (étape 5) ?
       * Les valeurs de config\database.php (port, utilisateur, mot de
         passe, nom de base) sont-elles correctes ?
       * Port 3306 déjà pris par un autre MySQL : changez le port dans
         XAMPP (my.ini) ou dans config\database.php.
   - Page blanche / erreur 500 : consultez  club-media\logs\app.log .
   - Images refusées : vérifiez que l'extension « gd » est activée dans
     php.ini (extension=gd), ainsi que « fileinfo » et « mbstring », puis
     redémarrez Apache. setup.php affiche l'état de chaque extension.
   - Scanner caméra noir : autorisez la caméra, utilisez localhost ou HTTPS,
     et vérifiez la connexion Internet (bibliothèque jsQR).
   - Mot de passe administrateur oublié : dans phpMyAdmin, videz la table
     « admins » (onglet Opérations > Vider), puis refaites l'étape 7.


=====================================================================
 PARTIE 2 — بالدارجة التونسية
=====================================================================

المتطلبات: XAMPP فيه PHP 8 ولا أكثر. ما تحتاجش Node.js ولا Composer.
مهم: الأنيميشن والـ QR code والسكانر يخدمو كان كي يكون عندك أنترنات
(المكتبات تتحمّل من CDN). من غير أنترنات الموقع يخدم أما بلا أنيميشن.

1) نزّل الـ ZIP و افتحو
   - نزّل club-media.zip.
   - كليك يمين > Extract All. باش يطلعلك دوسي اسمو club-media.

2) حطّو في XAMPP
   - انسخ دوسي club-media و حطو في:  C:\xampp\htdocs\
   - لازم يولّي عندك:  C:\xampp\htdocs\club-media\index.php
   - ٱنتبه ما يكونش مكرّر (club-media داخل club-media).

3) شغّل Apache و MySQL
   - افتح XAMPP Control Panel.
   - كليكي على Start قدّام Apache و قدّام MySQL.
   - لازم يولّيو الزوز بالأخضر.

4) افتح phpMyAdmin
   - في المتصفح اكتب:  http://localhost/phpmyadmin

5) اعمل Import لـ database.sql
   - كليكي على «Importer / Import».
   - اختار الملف  club-media\database.sql
   - كليكي «Importer» لتحت. السكريبت يعمل وحدو قاعدة البيانات
     club_media_db والجداول و3 أحداث كمثال.
   - تنجم تعاود الـ import من غير ما تخسر شي.

6) ظبط الاتصال بقاعدة البيانات
   - افتح  club-media\config\database.php  بالـ Notepad.
   - XAMPP عادي: ما تبدّل حتى شي:
       DB_HOST = localhost   DB_PORT = 3306
       DB_NAME = club_media_db
       DB_USER = root        DB_PASS = ''  (فارغ)
   - كان MySQL عندك بورت آخر (مثلا 3307) ولا كلمة سر، بدّل القيم هاذم.
     هذا الملف الوحيد اللي تبدّل فيه الاتصال.

7) اعمل حساب الأدمين (مرة وحدة برك)
   - افتح  http://localhost/club-media/setup.php
   - تأكد أنو كل السطور خضراء (✔).
   - اختار اسم مستخدم و email و كلمة سر
     (10 حروف على الأقل، فيها حرف وعدد).
   - ما فماش حساب جاهز مسبقا. بعد ما تعمل الحساب الأول الصفحة تتسكّر وحدها.
     كي تنشر الموقع على هبرجمو حقيقي امسح ملف setup.php.

8) افتح الموقع
   - http://localhost/club-media/

9) افتح لوحة الأدمين
   - http://localhost/club-media/admin.php
   - ادخل بالحساب اللي عملتو في المرحلة 7.

10) زيد أول حدث
   - قائمة «Événements» > «Nouvel événement».
   - عمّر العنوان، الوصف، التاريخ، الوقت، المكان، نوع التذكرة
     (مجانية ولا بالفلوس)، الثمن بالدينار، عدد الأماكن.
   - زيد تصويرة (JPG ولا PNG ولا WebP)، حطّ «Publié» و سجّل.
   - الأحداث متاع المثال تنجم تبدّلهم ولا تفسخهم.

11) ارفع أول فيديو
   - قائمة «Vidéos» > زيد فيديو.
   - ولا تختار ملف MP4/WebM (حتى 200 Mo)، ولا تلصق رابط YouTube.
   - اختار الحدث المرتبط، حط «Publié» و سجّل.
   - كان الفيديو كبير و ما يتحمّلش: افتح C:\xampp\php\php.ini
     و كبّر upload_max_filesize و post_max_size (مثلا 210M و 220M)
     وبعد أعد تشغيل Apache.

12) جرّب تذكرة و تثبّت منها
   أ) من الموقع: افتح حدث > «Réserver»، اكتب الاسم، email،
      الهاتف و العدد، و ثبّت.
      - حدث مجاني: التذكرة صالحة على طول مع QR code.
      - حدث بالفلوس: الحالة «En attente de paiement». ما فماش خلاص
        أونلاين: التذكرة ما تصلحش كان الأدمين يأكّد الخلاص بيدو.
   ب) تأكيد الخلاص: لوحة الأدمين > «Réservations» > «Confirmer le paiement».
   ج) التثبّت: لوحة الأدمين > «Tickets» (السكانر). اسمح للكاميرا
      (يخدم على localhost ولا HTTPS) و قرّب الـ QR code، ولا اكتب
      كود التذكرة بيدك.
      النتائج: صالحة / مستعملة من قبل / ملغاة / غير مؤكدة / غير صحيحة.
      التذكرة الصالحة تتسجّل «مستعملة» و ما تنجمش تدخل بيها مرة ثانية.
   د) الزائر يلقى تذكرتو في «Mes réservations» بالـ email و كود الحجز (R-XXXXXXXX).

13) مشاكل و حلولها
   - خطأ 404 Not Found:
       * الدوسي لازم يكون بالضبط  C:\xampp\htdocs\club-media
       * العنوان:  http://localhost/club-media/
       * Apache لازم يكون شاعل (أخضر).
   - خطأ الاتصال بقاعدة البيانات:
       * MySQL شاعل (أخضر)؟
       * عملت Import لـ database.sql (المرحلة 5)؟
       * القيم في config\database.php صحيحة (بورت، user، كلمة سر، اسم قاعدة)؟
   - صفحة بيضاء ولا خطأ 500: شوف الملف  club-media\logs\app.log
   - التصاور ما تتقبلش: فعّل extension=gd و fileinfo و mbstring في php.ini
     و أعد تشغيل Apache. صفحة setup.php تورّيك شكون ناقص.
   - كاميرا السكانر سوداء: اسمح للكاميرا، خدم على localhost ولا HTTPS،
     و تأكد أنّ عندك أنترنات.
   - نسيت كلمة سر الأدمين: في phpMyAdmin فرّغ جدول admins ثم عاود المرحلة 7.


=====================================================================
 PARTIE 3 — INFORMATIONS TECHNIQUES
=====================================================================

Arborescence : voir les dossiers  config/ includes/ assets/ admin/ uploads/ logs/
  (admin.php = connexion ; verify.php = accès au scanner, réservé aux admins).

Sécurité mise en place :
  - Requêtes PDO préparées ; mots de passe hachés (password_hash).
  - Sessions sécurisées (cookies HttpOnly, SameSite, régénération de l'ID
    après connexion), jetons CSRF, échappement des sorties.
  - Limitation de débit (connexion, réservation, contact) via table rate_limits.
  - Envois de fichiers : vérification du type réel (signature), des
    dimensions et de la taille ; noms aléatoires ; uploads/.htaccess
    interdit toute exécution de script.
  - Tickets : jeton aléatoire de 48 caractères dans le QR code (jamais
    l'identifiant séquentiel) ; réservations en transaction avec
    verrouillage de ligne (SELECT … FOR UPDATE) contre la survente.
  - Erreurs : jamais affichées aux visiteurs ; journal dans logs/app.log.

Hébergement mutualisé PHP/MySQL :
  1. Créez la base et l'utilisateur dans le panneau de l'hébergeur.
  2. Importez database.sql (retirez les lignes CREATE DATABASE / USE si
     l'hébergeur impose son propre nom de base).
  3. Modifiez config/database.php (hôte, base, utilisateur, mot de passe).
  4. Envoyez les fichiers, vérifiez que uploads/ et logs/ sont inscriptibles.
  5. Passez par setup.php, puis SUPPRIMEZ setup.php.
  6. Activez HTTPS. Vous pouvez aussi définir SETUP_KEY dans config/config.php.
  7. Les valeurs php_value du .htaccess ne fonctionnent qu'avec mod_php ;
     sinon réglez les limites d'envoi dans l'hébergeur (.user.ini).

Contenu à compléter : la page « À propos » contient des textes
à compléter, volontairement non inventés (histoire, dates, membres). Remplacez-les dans Administration > Paramètres avec des informations vérifiées.
