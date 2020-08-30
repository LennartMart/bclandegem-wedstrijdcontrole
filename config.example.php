<?php
/**
 * User: Lennart
 */

define('PLOEGNAAM', '');
define('BADMINTONVLAANDEREN_URLS', serialize (array()));

define('EMAIL_USERNAME', '');
define('EMAIL_PASSWORD', '');
define('EMAIL_ONTVANGERS', serialize (array("")));

$config_ploegen = array();
$config_ploegen[] = new Ploeg();

define('PLOEGEN', serialize($config_ploegen));


class Ploeg {
  public $kapitein;
  public $ploegNaam;
  public $email;
  public $fouten;
  
  public function __construct($kapitein, $ploegNaam, $email) {
    $this->kapitein = $kapitein;
	$this->ploegNaam = $ploegNaam;
	$this->email = $email;
	$this->fouten = array();
  }
}

