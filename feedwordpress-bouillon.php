<?php
  /*
    Plugin Name: FeedWordPress Bouillon
    Plugin URI: http://www.geobib.fr/bouillon
    Description: Enrichit Feedwordpress pour en faire un bouillon (outil de veille commun à plusieurs personnes)
    Author: Sylvain Machefert
    
    Historique
      20111028
        - Ajout de l'extraction des mots-clés depuis Diigo
      
      20111026
        - Gestion du nombre de veilleurs dans les titres au niveau du plugin et plus dans le template directement
        
      20111015 (0.1)
        - Première version
    
    TODO
      - Ajouter un dashboard pour contrôler les informations principales du plugin (quoi ?)

  */
  global $bouillon_db_version;
  
  define(BOUILLON_DB_VERSION, "0.1");
  define(TABLE_MAILING_BOUILLON, "mailing_bouillon");
  
  # On ajoute une fonction qui va faire un filtrage avancé des éléments partagés
  add_filter('syndicated_post', 'fwp_check_dup_bouillon', 10, 2);
  
  # On ajoute une fonction qui va ajouter à la fin du titre le nombre de veilleurs
  # ayant partagé le lien s'il est supérieur à 1 (évite d'avoir à modifier le template)
  add_filter('the_title', 'override_post_title_with_number_veilleurs', 10, 2);

  # On va faire en sorte de n'afficher que le début du post, et si on est sur
  # une page de résultats de recherche, on va surligner le texte recherché
  add_filter( 'get_the_excerpt', 'bouillon_custom_excerpt_more' );

  // On va faire en sorte de créer une table qui nous servira à stocker les membres inscrits
  register_activation_hook(__FILE__,'bouillon_install');

  add_action('admin_menu', 'bouillon_add_menu');
  
//  add_action('init', '');
  
  define( 'HEADER_IMAGE_WIDTH', 940);
  define( 'HEADER_IMAGE_HEIGHT', 200 );
  function header_style() {
      ?><style type="text/css">
          #header {
              background: url(<?php header_image(); ?>) no-repeat;
              height:220px;
              margin-top:15px;
          }
      </style><?php
  }

  function admin_header_style() {
      ?><style type="text/css">
          #header {
              background: url(<?php header_image(); ?>);
          }
      </style><?php
  }
  add_custom_image_header('header_style', 'admin_header_style');
  
  function bouillon_custom_excerpt_more( $output ) {
    global $post;

    if (is_search())
    {
      // Si on est en train de faire une recherche on va essayer d'identifier la zone de l'article
      // dans laquelle se trouve le terme recherché
      $keys= explode(" ", get_search_query());
      $output = "";
      $cpt = 0;
      $mon_post = $post->{'post_content'};
      while (preg_match('/(.*)('.implode("|", $keys).')(.*)$/umi', $mon_post, $matches))
      {
        $debut = $matches[1];
        $mot = $matches[2];
        $mon_post = $matches[3];

        if ($output != "")
        {
          $output .= "<br/>&lt;...$gt;<br/>";
        }
        $output.= "[...] ".mb_substr($debut, (strlen($debut) - 60)).'<strong class="search-excerpt" style="color:red">'.$mot.'</strong>'.mb_substr($mon_post, 0, 60)." [...]";
      }
    }


    $output .= '<br/><a href="'. get_permalink() . '"><span class="meta-nav">&rarr;</span> Lire l\'article original</a>';
  	return $output;
  }

  remove_all_actions( 'do_feed_rss2' );
  add_action( 'do_feed_rss2', 'bouillon_feed_rss2', 10, 1 );

  function bouillon_feed_rss2( $for_comments ) {
      $rss_template = get_template_directory() . '/feed-rss2.php';
      if( file_exists( $rss_template ) )
          load_template( $rss_template );
      else
          do_feed_rss2( $for_comments ); // Call default function
  }


  # Détection des doublons et enregistrement des informations particulières
  # Génération d'un post_excerpt à partir du début du content
  function fwp_check_dup_bouillon ($content, $post) {
    global $wpdb;
    $title = $content["post_title"];
    $guid = $content["guid"];
    if ($guid != "tag:google.com,2005:reader/item/a78f7247fa0ec02b")
    {
//      return NULL;
    }
  
    $dup_id = "";

    $content["meta"]["bouillon_mail_nectar"] = "0";
    $content["meta"]["bouillon_mail_global"] = "0";
    
    // Ici, on extrait le commentaire de partage de Google Reader
    // On le stocke dans la variable commentaire et on l'utilisera
    // plus tard pour mettre à jour le post ou créer un nouveau post
    $contenu = $content["post_content"];
    /*****************************************/
    /* GESTION SPÉCIFIQUE POUR GOOGLE READER */
    /*****************************************/
    // On va récupérer le commentaire directement là où Google le stocke
    $commentaire = $post->{"item"}["gr"]["annotation_atom_content"];
    if ($commentaire != "")
    {
      // On fait sauter le commentaire en blockquote
      if (preg_match("/^<blockquote>Shared(.*?)<\/blockquote>(.*)/is", $contenu, $matches))
      {
        $dummy = $matches[1];
        $content["post_content"] = $matches[2];
      }
      
      // Pour indiquer l'auteur du commentaire, on va aller regarder ce qu'on trouve
      // dans 
      $auteur_commentaire = $post->{"item"}["gr"]["annotation_author_name"];
      // Quand nom Google Reader est différent de nom de catégorie on fait une mise à jour
      // ici (à améliorer ....)
      if ($auteur_commentaire == "Bibliobsession, Silvae")
      {
        $auteur_commentaire =  "Bibliobsession";
      }
      
      $auteur_commentaire_cat  = get_term_by('name', $auteur_commentaire , 'category');
      if ($auteur_commentaire_cat  )
      {
        $id_category_auteur = $auteur_commentaire_cat->{"term_id"};
        // Si on arrive à retrouver une catégorie identique au nom reader de l'utilisateur, on l'utilise
        // Ca évite de doubler les commentaires quand quelqu'un reposte le partage d'un autre veilleur
        $commentaire = $id_category_auteur."####".$commentaire;
      }
      else
      {
        // Sinon on considère que c'est la personne qui a posté qui est l'auteur du commentaire
        $commentaire = $content["tax_input"]["category"][0]."####".$commentaire;
      }
    }

    /*********************************/
    /* GESTION SPÉCIFIQUE POUR DIIGO */
    /*********************************/
    if (preg_match("|tag:www.diigo.com|", $guid))
    {
      // Ici on va extraire les tags Diigo, on les mettra à la fin à l'aide de la fonction wp_set_post_terms
      if (preg_match("/(.*)<p><strong>Tags:<\/strong>(.*)/is", $contenu, $matches))
      {
          $content["post_content"] = $matches[1];
          $liste_tags = $matches[2];
          // Suppression du mot clé bouillon, inutile
          $liste_tags = preg_replace("/<a[^>]*>bouillon<\/a>/", "", $liste_tags);
          // On va adapter cette liste de mots clés pour pouvoir l'importer par la suite :
          // suppression des liens.
          $liste_tags = preg_replace("/<\/a>/is", ",", $liste_tags);
          $liste_tags = preg_replace("/<a[^>]*>/is", "", $liste_tags);
      }

      // On supprime un commentaire vide
      $content["post_content"] = preg_replace("/<p>(.*)<\/p>/", "$1", $content["post_content"]);
      // On vide si on n'a plus que des lignes vides
      $content["post_content"] = preg_replace("/^\s*$/", "", $content["post_content"]);

      // Ici sur Diigo s'il reste quelque chose c'est un commentaire du veilleur
      if ($content["post_content"] != "")
      {
        $commentaire = $content["tax_input"]["category"][0]."####".$content["post_content"];
      }
      $content["post_content"] = "";
      $content["post_excerpt"] = "";
    }
    
    /********************************************************/
    /* Gestion spécifique pour la sentinelle via Feedburner */
    /********************************************************/
    if ($content["meta"]["syndication_source_id"] == "http://feeds.feedburner.com/sentinelle_jvbib")
    {
      // On va faire sauter tout le post_content qui ne fait rien remonter : pas de commentaire
      $content["post_content"] = "";
      $content["post_excerpt"] = "";
    }
  
    # Génération de l'excerpt qu'on va utiliser par défaut. 140 caractères en mode tweet
    $contenu = $content["post_content"];
    $excerpt = $contenu;
    if ( strlen(trim($contenu)) > 0 ) :
      $excerpt = strip_tags($contenu);
      if (strlen($excerpt) > 142) :
        $excerpt = mb_substr($excerpt,0,140).'...';
      endif;
		endif;

    
    $content["post_excerpt"] = $excerpt;
    
    // On va regarder le permalink et le changer si
    $permalink = $content['meta']['syndication_permalink'];
    // On gère les redirection via feedproxy de Google Reader
    if (preg_match("/feedproxy\.google\.com/", $permalink))
    {
      // On va récupérer le redirect
      $cible = get_redirect_url($permalink);
      if ( ($cible) and ($cible != $permalink) )
      {
        $permalink = $cible;
      }
    }
    
    // On supprime les paramètres ajoutés en fin de lien par le syndicateur : ?utm=...
    $permalink = preg_replace("/\?utm.*/", "", $permalink);
    
    // On met à jour le lien permanent du post
    $content['meta']['syndication_permalink'] = $permalink;
  
  

    // Contrôle sur le syndication_permalink
    $sql = $wpdb->prepare( "
        SELECT post_id FROM $wpdb->postmeta
        WHERE 
        (meta_key = 'syndication_permalink' AND meta_value = '%s')",
        esc_html($content['meta']['syndication_permalink'])
    );

    $row = $wpdb->get_row( $sql, ARRAY_A, 0);
    if ($row)
    {
      $dup_id = $row["post_id"];
    }

    // Contrôle sur le titre du post
    // On ne fait pas ce contrôle pour les posts qui s'intituleraient Untitled
    if ( (!$dup_id) and ($title != "Untitled") )
    {
      $sql = $wpdb->prepare( "
        SELECT ID FROM $wpdb->posts
        WHERE 
        (post_title = '%s' OR post_title = '%s')",
        esc_html($title),
        $title
      );
      
      $row = $wpdb->get_row( $sql, ARRAY_A, 0);
      if ($row)
      {
        $dup_id = $row["ID"];
      }  
    }

    // On est sur un lien partagé qui a déjà été partagé par un autre veilleur
    if ($dup_id)
    {
      // On ajoute le commentaire seulement
      if ($commentaire)
      {
        // On doit vérifier que le commentaire n'existe pas déjà pour éviter d'avoir deux fois le même
        // commentaire si quelqu'un partage un billet déjà partagé dans le bouillon avec un commentaire
        $doublon_comm = false;
        $meta_values = get_post_meta($dup_id, "bouillon-commentaire");
        foreach ($meta_values as $un_commentaire)
        {
          if ($un_commentaire == $commentaire)
          {
            $doublon_comm = true;
          }
        }
        
        if (!$doublon_comm)
        {
          // On ajoute le commentaire s'il n'est pas déjà présent
          // Ca évite de poster deux fois le commentaire si une veille a été reveillé par un autre
          add_post_meta($dup_id, "bouillon-commentaire", $commentaire, false);
        }
      }

      // On met à jour le contenu s'il était vide. Ça peut être le cas si le premier
      // utilisateur a partagé depuis Diigo et le second depuis Google Reader par exemple
      $content_post_dup = get_post($dup_id);
      $content_dup = $content_post->post_content;
      if ( ($content_dup == "") and ($content["post_content"] != "") )
      {
        $tab_post_update = Array();
        $tab_post_update['ID'] = $dup_id;
        $tab_post_update['post_content'] = $content["post_content"];
        $tab_post_update['post_excerpt'] = $content["post_excerpt"];
        wp_update_post( $tab_post_update );
      }


      // TODO : Vérifier ce qui se passe exactement ici 
      foreach ($content["tax_input"]["category"] as $une_cat)
      {
        // On met simplement à jour les catégories du post
        $tab_cat = array_unique(array_merge(wp_get_post_categories( $dup_id ), Array($une_cat)));
        
        // On met à jour les catégories du post
        wp_set_post_categories( $dup_id, $tab_cat);
        
        // On stocke le nombre de veilleurs (pour ajouter le +)
        delete_post_meta($dup_id, "bouillon_nb_veilleurs");
        add_post_meta($dup_id, "bouillon_nb_veilleurs", sizeof($tab_cat), true);
        
        // On ajoute les nouveaux tags au post
        wp_set_post_terms( $dup_id, $liste_tags, "", true); 
      }

    }
    else
    {
      if ($commentaire)
      {
        $content["meta"]["bouillon-commentaire"] = $commentaire;
      }
      
      // On va ajouter les tags
      $tab_tags = split(", ?", $liste_tags);
      foreach ($tab_tags as $un_tag)
      {
        $content["tax_input"]["post_tag"][] = $un_tag;
      }
      return $content;
    }
  } /* fwp_add_source_to_content() */


  function override_post_title_with_number_veilleurs($title, $_post = null){
    global $post, $wp_query, $content, $page, $pages;
    $nb = get_post_custom_values("bouillon_nb_veilleurs", get_the_ID());
    $out_title = $title; # the_title();
    if ($nb)
    {
      $out_title .= " (+".$nb[0].")";
    }
		return $out_title;


    static $hasOverriden = false;
    if(is_singular() &&
       !$hasOverriden &&
       in_the_loop() &&
       ( !is_object($_post) || $wp_query->post->ID == $_post->ID ) &&
       $page == 1 &&
       preg_match('{^\s*<h(1|2)>(.+?)</h\1>\s*(.*)}s', $pages[0], $matches)
    ){
      $title = $matches[2];
      $pages[0] = $matches[3];
      $hasOverriden = true;
    }
    return $title;
  }
  
  function bouillon_install() {
   global $wpdb;
   $table_name = $wpdb->prefix . TABLE_MAILING_BOUILLON;
      
   $sql = "CREATE TABLE " . $table_name . " (
	  id mediumint(9) NOT NULL AUTO_INCREMENT,
	  mail varchar(255) NOT NULL,
	  type_envoi varchar(10),
    secret varchar(25),
    actif boolean,
	  UNIQUE KEY id (id)
    );";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    add_option("bouillon_db_version", BOUILLON_DB_VERSION);
  }
  
/**
* get_redirect_url()
* Gets the address that the provided URL redirects to,
* or FALSE if there's no redirect. 
*
* @param string $url
* @return string
*/
function get_redirect_url($url){
$redirect_url = null; 

$url_parts = @parse_url($url);
if (!$url_parts) return false;
if (!isset($url_parts['host'])) return false; //can't process relative URLs
if (!isset($url_parts['path'])) $url_parts['path'] = '/';

$sock = fsockopen($url_parts['host'], (isset($url_parts['port']) ? (int)$url_parts['port'] : 80), $errno, $errstr, 30);
if (!$sock) return false;

$request = "HEAD " . $url_parts['path'] . (isset($url_parts['query']) ? '?'.$url_parts['query'] : '') . " HTTP/1.1\r\n"; 
$request .= 'Host: ' . $url_parts['host'] . "\r\n"; 
$request .= "Connection: Close\r\n\r\n"; 
fwrite($sock, $request);
$response = '';
while(!feof($sock)) $response .= fread($sock, 8192);
fclose($sock);

if (preg_match('/^Location: (.+?)$/m', $response, $matches)){
  if ( substr($matches[1], 0, 1) == "/" )
    return $url_parts['scheme'] . "://" . $url_parts['host'] . trim($matches[1]);
  else
    return trim($matches[1]);

} else {
  return false;
}

}

/**********************************************************************************
 **********************************************************************************
 **********************************************************************************
 **********************************************************************************
 **********************************************************************************
 * À PARTIR D'ICI CE SONT DES FONCTIONS OBSOLÈTES (EN PARTICULIER ENVOI MAILING)  *
 **********************************************************************************
 **********************************************************************************
 **********************************************************************************
 **********************************************************************************
 **********************************************************************************/
    
    
  function bouillon_add_menu()
  {
    $page = add_options_page("Le bouillon", "Le bouillon", 8, basename(__FILE__), "bouillon_display");
    add_action('admin_print_scripts-' . $page, 'smbd_print_scripts');
  }
    
  function bouillon_display()
  {
    bouillon_request_handler();
    ?>
    <h2>Le bouillon</h2>
    <h3>Envoi par mail</h3>
    <ul>
      <li><a href='options-general.php?page=feedwordpress-bouillon.php&envoi_bouillon=nectar'>Envoyer le nectar</a></li>
      <li><a href='options-general.php?page=feedwordpress-bouillon.php&envoi_bouillon=global'>Envoyer l'ensemble des nouveaux articles</a></li>
    </ul>
    <?php
  }
  
  function bouillon_request_handler()
  {
    global $wpdb;
    // Pour bien faire, ici il faudrait faire une vérif pour tester
    // que la demande d'envoi n'a pas été faite pas n'importe qui (avec une clé ?)
    if (isset($_GET["envoi_bouillon"]))
    {
      $type_envoi = $_GET["envoi_bouillon"];
      $args = array();
      $sujet_mail = "";

      if ($type_envoi == "nectar")
      {
        $args = array(
          'meta_query' => array(
            array(
              'key' => 'bouillon_mail_nectar',
              'value' => '1',
              'compare' => 'NOT LIKE'
            ),
            array(
              'key' => 'bouillon_nb_veilleurs',
              'value' => '1',
              'compare' => '>',
              'type' => 'numeric'
            )
          )
        );
        $sujet_mail = "Le bouillon des bibliobsédés - Nectar du ".date("d/m/Y");
      }
      elseif ($type_envoi == "global")
      {
        $args = array(
          'meta_query' => array(
            array(
              'key' => 'bouillon_mail_global',
              'value' => '1',
              'compare' => 'NOT LIKE'
            )
          )
        );
        $sujet_mail = "Le bouillon des bibliobsédés - Nouveaux billets [".date("d/m/Y")."]";
      }
      $query = new WP_Query( $args );
      if ((int)$query->found_posts > 0)
      {
        $corps_mail = "<body>";
        $corps_mail .= "<p>Aujourd'hui, $query->found_posts nouveaux articles dans le ";
        if ($type_envoi == "nectar")
        {
          $corps_mail .= "nectar du ";
        }
        $corps_mail .= "bouillon</p>";
        while ( $query->have_posts() )
        {
          $query->the_post();
          $corps_mail .= "<br/><h1 style='font-size:0.9em'>".get_the_title()."</h1>";
          $corps_mail .= '<div style="border:2px solid #FFc369; color:black; padding:5px; margin:0px">';
          // On commence par afficher la liste des veilleurs ayant partagé le billet
          $categories_list = get_the_category_list( __( ', ', 'twentyeleven' ) );
          $corps_mail .= "<p style='padding:0px; margin:0px'><span style=''>Partagé par : </span>".$categories_list."</p>";
              
          // On va afficher la liste des commentaires sur ce billet
          $mykey_values = get_post_custom_values('bouillon-commentaire', get_the_ID());
          if (sizeof($mykey_values))
          {
            $corps_mail .= "<span>Commentaire(s) des veilleurs : </span>";
            $corps_mail .= "<dl style='padding-left:20px; margin-bottom:0px;'>";
            foreach ( $mykey_values as $key => $value ) {
              $tab_comm = split("####", $value);
              $corps_mail .= "<dt style='float:left'>".get_cat_name($tab_comm[0])."&nbsp;:&nbsp;</dt>";
              $corps_mail .= "<dd style='margin-bottom:0px;'>".$tab_comm[1]."</dd>";
            }
            $corps_mail .= "</dl>";
          }
          $tab_source = get_post_custom_values('syndication_source_original', get_the_ID());
          $nom_source = $tab_source[0];
          if ($tab_source)
          {
            $corps_mail .= "<p style='font-size:1.2em; margin-bottom:0px'><span style=''>Source : </span>".$nom_source."</p>";
          }
          $corps_mail .= "</div><!-- .entry-meta -->";
          $corps_mail .= "<p>".get_the_excerpt()."</p>";
          
          // On va mettre à jour notre base pour que cette notice ne réaparaisse plus.
          update_post_envoi(get_the_id(), $type_envoi);
        }
        
        $corps_mail .= "</body>";
        
        add_filter('wp_mail_content_type',create_function('', 'return "text/html";'));
        $headers = 'From: Le bouillon des bibliobsédés <bouillon@geobib.fr>' . "\r\n";
        
        // A partir d'ici on va faire l'envoi à toutes les personnes
          
        $wpdb->show_errors();
        $table_mailing = $wpdb->prefix . TABLE_MAILING_BOUILLON;
        $rows = $wpdb->get_results("select * from $table_mailing;");
        foreach ($rows as $row)
        {
          print "Envoi à ".$row->{"mail"}."<br/>";
          wp_mail( "smachefert@gmail.com", $sujet_mail, $corps_mail, $headers);
        }
        print "<br/><div class='updated'>Mail envoyé avec succès</div>";
      }
      else
      {
        print "<br/><div class='updated'><span style='color:red; font-weight:bold'>Information</span> : Aucun nouveau post à envoyer</div>";
      }
    } 
  }
  
  function update_post_envoi($post_id, $type_envoi)
  {
    // On indique que le post a déjà été envoyé par mail une fois
    update_post_meta($post_id, "bouillon_mail_".$type_envoi, "1");
    // On stocke la date d'envoi de ce post dans le mail global
    add_post_meta($post_id, "bouillon_mail_".$type_envoi."_date", date("Y-m-d"));
  }

  // Cette fonction affiche le formulaire sur le côté de l'écran
  // désactivé pour le moment pour éviter les problèmes avec la
  // fonction mail() de php qui limite les choses
  function bouillon_subscription()
  {
    global $wpdb;
    $mail = "";
    $sujet_mail = "";
    $corps_mail = "";

    if (isset($_GET["bouillon_email_confirmation"]))
    {
      // Le lecteur vient de cliquer sur une option de confirmation
      // dans l'email qu'on lui a envoyé
      $id = $_GET["user_id"];
      $secret = $_GET["secret"];
      $action = $_GET["action"];
      
      if ($action == "subscribe")
      {
        $ligne_inscrit = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix . TABLE_MAILING_BOUILLON." WHERE id = ".$id, OBJECT);
        if ($secret == $ligne_inscrit->secret)
        {
          $wpdb->update(
            $wpdb->prefix . TABLE_MAILING_BOUILLON, // Table
            array(
              "actif" => "1"
            ),
            array(
              "id" => $id
            )
          );
          
          print "<span style='color:red'>Votre inscription a bien été validée.</span>";
          $sujet_mail = "[Bouillon des bibliobsédés] Inscription enregistrée";
          $corps_mail = "Votre inscription au bouillon des bibliobsédés a bien été enregistrée. Vous commencerez à recevoir les mails d'ici peu.";
        }
        else
        {
          print "$secret // ".$ligne_inscrit->secret." [$mail]";
        }
      }
      elseif ($action == "unsubscribe")
      {
        // Suppression de l'enregistrement
        $wpdb->query(
          "
          DELETE FROM ".$wpdb->prefix . TABLE_MAILING_BOUILLON."
          WHERE id = ".$id
        );
        
        print "<span style='color:red'>Vous avez bien été désinscrit du bouillon</span>";
      }
    }
    elseif (isset($_POST["bouillon_email"]))
    {
      $mail = $_POST["bouillon_email"];
      $type_envoi = $_POST["type_inscription"];
      $inscription = $_POST["bouillon_inscription"];
      $secret = md5($mail.$type_envoi.time().rand());
      

      $ligne_inscrits = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix . TABLE_MAILING_BOUILLON." WHERE mail = '".$mail."'");
      
      if ($inscription == "1")
      {
        // On va d'abord vérifier que l'email n'est pas déjà enregistré dans la base
      
        if (sizeof($ligne_inscrits ) > 0)
        {
          print "<div style='color:red'>Cette adresse mail est déjà enregistrée</div>";
        }
        else
        {
          // On va enregistrer le lecteur dans la base
          $wpdb->insert(
            $wpdb->prefix . TABLE_MAILING_BOUILLON,
            array(
              'mail' => $mail,
              'type_envoi' => $type_envoi,
              'secret' => $secret,
              'actif' => '0'
            )
          );
          
          // On va envoyer le mail au lecteur pour lui indiquer comment
          $sujet_mail = "Confirmation d'inscription au bouillon des bibliobsédés";
          $lien_confirmation = "http://www.geobib.fr/bouillon/index.php?bouillon_email_confirmation=1&user_id=".$wpdb->insert_id."&secret=$secret&action=subscribe";
          $corps_mail = "Une demande d'abonnement au bouillon des bibliobsédés vient d'être effectuée pour votre adresse mail.<br/>";
          $corps_mail .= "Pour confirmer cette inscription, <a href='$lien_confirmation'>visitez cette page</a><br/><br/>";
          $corps_mail .= "Si le lien ci-dessus n'est pas actif copiez/collez le lien suivant dans votre navigateur : $lien_confirmation";
        }
        
        print "<span style='font-weight:bold; color:#FF3333'>Un mail de confirmation vient de vous être envoyé.</span><br/><br/>";
      }
      elseif ($inscription == "0")
      {
        // Demande de désabonnement
        if (sizeof($ligne_inscrits ) == 0)
        {
          print "<span style='color:red'>Cette adresse mail est inconnue dans la base</span>";
        }
        else
        {
          $sujet_mail = "Confirmation de désabonnement au bouillon des bibliobsédés";
          $lien_confirmation = "http://www.geobib.fr/bouillon/index.php?bouillon_email_confirmation=1&user_id=".$ligne_inscrits[0]->id."&secret=$secret&action=unsubscribe";
          $corps_mail = "Une demande de désabonnement au bouillon des bibliobsédés vient d'être effectuée pour votre adresse mail.<br/>";
          $corps_mail .= "Pour confirmer cette désinscription, <a href='$lien_confirmation'>visitez cette page</a><br/><br/>";
          $corps_mail .= "Si le lien ci-dessus n'est pas actif copiez/collez le lien suivant dans votre navigateur : $lien_confirmation";
        
          print "<span style='color:red'>Un email de confirmation vient de vous être envoyé.</span>";
        }
      }
    }

    add_filter('wp_mail_content_type',create_function('', 'return "text/html";'));
    $headers = 'From: Le bouillon des bibliobsédés <bouillon@geobib.fr>' . "\r\n";
    
    if ($sujet_mail != "")
    {
      wp_mail( $mail, $sujet_mail, $corps_mail, $headers);
    }
    
    $out = '<form action="#bouillon_subscribe" method="post" style="border:1px solid #FFc369; padding:5px; margin-bottom:10px; border-radius:5px">' . "\n";
    $out .= "<p style='font-weight:bold; margin:0px; padding:0px'>Recevoir le bouillon par mail</p>";
    $out .= '<p style="margin:0px; padding:0px">';
    $out .= '<br />Adresse : <input type="text" name="bouillon_email" id="bouillon_email" size="18"/></p>' . "\n";
    $out .= "Recevoir : <input type='radio' name='type_inscription' value='global' checked='checked'/> Tout<input type='radio' name='type_inscription' value='nectar'/> Le nectar<br/>";
    $out .= "<hr style='margin:0px; padding:0px;'/>";
    $out .= '<p style="margin:0; padding:0"><input type="radio" name="bouillon_inscription" id="bouillon_inscription" checked="checked" value="1"/> '."Abonnement";
  	$out .= '<input type="radio" name="bouillon_inscription" id="bouillon_desinscription" value="0"/> Désabonnement</p>';
	
  	$out .= '<input type="submit" value="Valider"/>';
    $out .= "</form>";
    print $out;
  }
  
  


?>
