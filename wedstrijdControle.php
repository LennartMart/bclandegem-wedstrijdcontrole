<?php
/**
 * User: Lennart
 * Date: 11/11/14
 * Time: 22:51
 */
require_once("simple_html_dom.php");
require_once("config.php");
require 'PHPMailer/PHPMailerAutoload.php';


$matchenUitUrls = parseBadmintonVlaanderenUrls(unserialize(BADMINTONVLAANDEREN_URLS));

$huidigTijdstip = new DateTime("now",new DateTimeZone('Europe/Brussels'));
$fouten = array();

foreach ($matchenUitUrls as $match)
{
	if($huidigTijdstip > $match->tijdstip)
	{
		//$ploegNaam thuisploeg - check of de uitslag is ingevuld
		if (strpos($match->thuisPloeg,PLOEGNAAM) !== false)
		{
			//Thuisploeg
			if(empty($match->uitslag))
			{
				$fouten[] = "<li> <a href='" .  $match->link ."'>" . $match->getTitelMatch() . "</a> is nog niet ingevuld.</li>";
			}
		}
		else
		{
			//Uitploeg
			if(!empty($match->uitslag) && $match->goedgekeurd == false)
			{
				$fouten[] = "<li> <a href='" .  $match->link ."'>" . $match->getTitelMatch() . "</a> is nog niet bevestigd.</li>";
			}
		}
	}
}

if(!empty($fouten))
{
	sendMail($fouten);
}
else
{
	echo "Geen fouten gevonden!";
}
	
	

	
function parseBadmintonVlaanderenUrls($badmintonUrls)
{
    $lijstMatchen = array();
    foreach($badmintonUrls as $url)
    {
        //haal de html op
        $html = file_get_html($url);
        //Haal alle rijen op in de wedstrijden tabel
        $rijen = $html->find('table[class=ruler] tr');
		if(empty($rijen))
		{
			//Tijdelijk geen e-mail sturen - tabellen zijn gewoon leeg
			echo "Geen rijen gevonden op ".  $url . "<br/>";
			//sendMail(array("<li>De tabelstructuur van Badminton Vlaanderen kon niet worden ingelezen. Mogelijks is de website gewijzigd, of zijn er geen matchen meer beschikbaar!</li>"));
		}
        //We hebben de eerste rij niet nodig - vreemde bug html_dom die tbody niet herkent als selector
        array_shift($rijen);
        foreach($rijen as $rij)
        {
            $match = new Match();
            $celnummer = 0;
            foreach($rij->find('td') as $cel)
            {
                switch($celnummer){
                    case 1: //TIJDSTIP
                        //Verwijder dagaanduiding, niet nodig
                        $tijdstip = substr($cel->innertext(),3);
                        $tijdstip = strip_tags($tijdstip);
                        $match->tijdstip = DateTime::createFromFormat("d/m/Y H:i", $tijdstip, new DateTimeZone('Europe/Brussels'));
                        break;
                    case 6: //THUISPLOEG
                        //Vind de link in deze cel
                        $linkPloeg = $cel->find('a');
                        //En neem de ploegnaam
                        $match->thuisPloeg = $linkPloeg[0]->innertext();
                        $match->link = "http://badmintonvlaanderen.be/sport/" . $linkPloeg[0]->href;
                        break;
                    case 8: //UITPLOEG
                        //Vind de link in deze cel
                        $linkPloeg = $cel->find('a');
                        //En neem de ploegnaam
                        $match->uitPloeg = $linkPloeg[0]->innertext();
                        break;
                    case 9: //UITSLAG
                        $match->uitslag = $cel->innertext();
                        break;
                    case 10: //GECHECKT
                        if(($cel->find('img', 0))) {
                            $match->goedgekeurd = true;
                        }else{
                            $match->goedgekeurd = false;
                        }
                        break;
                }

                $celnummer++;
            }
            $lijstMatchen[] = $match;
        }
    }
    return $lijstMatchen;
}


function sendMail($fouten)
{
    $mail = new PHPMailer;
    $mail->isSMTP();                                      // Set mailer to use SMTP
    $mail->Host = 'smtp.gmail.com';                       // Specify main and backup server
    $mail->SMTPAuth = true;                               // Enable SMTP authentication
    $mail->Username = EMAIL_USERNAME;                   // SMTP username
    $mail->Password = EMAIL_PASSWORD;               // SMTP password
    $mail->SMTPSecure = 'tls';                            // Enable encryption, 'ssl' also accepted
    $mail->Port = 587;                                    //Set the SMTP port number - 587 for authenticated TLS
    $mail->setFrom(EMAIL_USERNAME, 'Wedstrijdcontrole competitie');     //Set who the message is to be sent from
    $recepients = unserialize(EMAIL_ONTVANGERS);
    foreach ($recepients as $recepient) {
        $mail->addAddress($recepient);
    }
    $mail->WordWrap = 50;                                 // Set word wrap to 50 characters
    $mail->isHTML(true);                                  // Set email format to HTML

    $mail->Subject = 'Actie vereist op Badminton Vlaanderen';
    $bodyText = 'Beste, <br/><br/>Er zijn enkele matchen die aandacht vereisen: <br/><br/><ul>';
    foreach ($fouten as $fout) {
        $bodyText = $bodyText . $fout;
    }
    $bodyText = $bodyText . "</ul><br/>Met vriendelijke groeten,<br/><br/>Wedstrijdcontrole service";

    $mail->Body    = $bodyText;
    $mail->AltBody = 'Er zijn enkele waarschuwingen. Bezoek www.badmintonvlaanderen.be en controleer de matchen.';

    if(!$mail->send()) {
        echo 'Message could not be sent.';
        echo 'Mailer Error: ' . $mail->ErrorInfo;
        exit;
    }

    echo 'Message has been sent';
}
class Match
{
    public $tijdstip;
    public $thuisPloeg;
    public $uitPloeg;
    public $goedgekeurd;
    public $uitslag;
    public $link;

    function getTitelMatch()
    {
        return $this->thuisPloeg . ' - ' . $this->uitPloeg;
    }
}



