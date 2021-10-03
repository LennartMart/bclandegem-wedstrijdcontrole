<?php

/**
 * User: Lennart
 * Date: 11/11/14
 * Time: 22:51
 */

use PHPMailer\PHPMailer\PHPMailer;

require_once("simple_html_dom.php");
require_once("config.php");

require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

// DEBUG
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

$aantalUrenOmUitslagInTeVullen = 36;
$aantalUrenOmTeBevestigen = 72;

$matchenUitUrls = parseBadmintonVlaanderenUrls(unserialize(BADMINTONVLAANDEREN_URLS));
$ploegen = unserialize(PLOEGEN);

$huidigTijdstip = new DateTime("now", new DateTimeZone('Europe/Brussels'));
$fouten = array();

foreach ($matchenUitUrls as $match) {
    if ($huidigTijdstip > $match->tijdstip) {
        //$ploegNaam thuisploeg - check of de uitslag is ingevuld
        if (strpos($match->thuisPloeg, PLOEGNAAM) !== false) {
            //Thuisploeg
            if (empty($match->uitslag)) {
                $tijdOver = berekenTijdTotBoete($huidigTijdstip, $match->tijdstip, $aantalUrenOmUitslagInTeVullen);
                $ploeg = vindPloeg($ploegen, $match->thuisPloeg);
                $ploeg->fouten[] = "<li> <a href='" .  $match->link . "'>" .
                    $match->getTitelMatch() . "</a> is nog niet ingevuld. Je hebt nog " . $tijdOver . " uren om de uitslag in te vullen!";
            }
        } else {
            //Uitploeg
            if (!empty($match->uitslag) && $match->goedgekeurd == false && $match->forfait == false) {
                $tijdOver = berekenTijdTotBoete($huidigTijdstip, $match->tijdstip, $aantalUrenOmTeBevestigen);
                $ploeg = vindPloeg($ploegen, $match->uitPloeg);
                $ploeg->fouten[] = "<li> <a href='" .  $match->link . "'>" . $match->getTitelMatch()
                    . "</a> is nog niet bevestigd.</li> . Je hebt nog " . $tijdOver . " uren om de uitslag te bevestigen!";
            }
        }
    }
}
foreach ($ploegen as $ploeg) {
    if (!empty($ploeg->fouten)) {
        sendMail($ploeg);
    } else {
        echo "Geen fouten gevonden voor " . $ploeg->ploegNaam . " <br/>";
    }
}

function berekenTijdTotBoete($huidigTijdstip, $tijdstipMatch, $officiëleTijd)
{
    $tijdVerschil = $huidigTijdstip->diff($tijdstipMatch);

    $urenVerschil = $tijdVerschil->h;
    $urenVerschil = $urenVerschil + ($tijdVerschil->days * 24);
    return $officiëleTijd - $urenVerschil;
}
function parseBadmintonVlaanderenUrls($badmintonUrls)
{
    $lijstMatchen = array();
    foreach ($badmintonUrls as $url) {
        //haal de html op
        $html = file_get_html($url);
        //Haal alle rijen op in de wedstrijden tabel
        $rijen = $html->find('table[class=ruler] tr');

        if (empty($rijen)) {
            //Tijdelijk geen e-mail sturen - tabellen zijn gewoon leeg
            echo "Geen rijen gevonden op " .  $url . "<br/>";
            //sendMail(array("<li>De tabelstructuur van Badminton Vlaanderen kon niet worden ingelezen. Mogelijks is de website gewijzigd, of zijn er geen matchen meer beschikbaar!</li>"));
        }
        //We hebben de eerste rij niet nodig - vreemde bug html_dom die tbody niet herkent als selector
        array_shift($rijen);
        foreach ($rijen as $rij) {
            $match = new Wedstrijd();
            $celnummer = 0;
            foreach ($rij->find('td') as $cel) {
                switch ($celnummer) {
                    case 1: //TIJDSTIP
                        //Verwijder dagaanduiding, niet nodig
                        $tijdstip = substr($cel->innertext(), 3);
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
                        if (strpos($match->uitslag, "Niet gespeeld") !== false) {
                            $match->forfait = true;
                        } else {
                            $match->forfait = false;
                        }
                        break;
                    case 10: //GECHECKT
                        if (($cel->find('img', 0))) {
                            $match->goedgekeurd = true;
                        } else {
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


function sendMail($ploeg)
{
    $mail = new PHPMailer;
    $mail->isSMTP();                                      // Set mailer to use SMTP
    $mail->Host = 'mail.bclandegem.be';                       // Specify main and backup server
    $mail->SMTPAuth = true;                               // Enable SMTP authentication
    $mail->Username = EMAIL_USERNAME;                   // SMTP username
    $mail->Password = EMAIL_PASSWORD;               // SMTP password
    $mail->SMTPSecure = 'tls';                            // Enable encryption, 'ssl' also accepted
    $mail->Port = 587;                                    //Set the SMTP port number - 587 for authenticated TLS
    $mail->setFrom(EMAIL_USERNAME, 'Wedstrijdcontrole competitie');     //Set who the message is to be sent from

    $mail->addAddress($ploeg->email);
    $ontvangers = unserialize(EMAIL_ONTVANGERS);
    foreach ($ontvangers as $ontvanger) {
        $mail->addAddress($ontvanger);
    }
    $mail->WordWrap = 50;                                 // Set word wrap to 50 characters
    $mail->isHTML(true);                                  // Set email format to HTML

    $mail->Subject = 'Actie vereist op Badminton Vlaanderen';
    $bodyText = 'Beste ' . $ploeg->kapitein . ', <br/><br/>Er zijn één of meerdere matchen die aandacht vereisen: <br/><br/><ul>';
    foreach ($ploeg->fouten as $fout) {
        $bodyText = $bodyText . $fout;
    }
    $bodyText = $bodyText . "</ul><br/>Met vriendelijke groeten,<br/><br/>BC Landegem - Wedstrijdcontrole Service";

    $mail->Body    = $bodyText;
    $mail->AltBody = 'Er zijn enkele waarschuwingen. Bezoek www.badmintonvlaanderen.be en controleer de matchen.';

    if (!$mail->send()) {
        echo 'Message could not be sent. <br/>';
        echo 'Mailer Error: ' . $mail->ErrorInfo;
        exit;
    }

    echo 'Bericht goed verzonden! <br/>';
}

function vindPloeg($ploegen, $ploegNaam)
{
    foreach ($ploegen as $ploeg) {
        if ($ploegNaam == $ploeg->ploegNaam) {
            return $ploeg;
        }
    }
    return null;
}

class Wedstrijd
{
    public $tijdstip;
    public $thuisPloeg;
    public $uitPloeg;
    public $goedgekeurd;
    public $uitslag;
    public $link;
    public $forfait;

    function getTitelMatch()
    {
        return $this->thuisPloeg . ' - ' . $this->uitPloeg;
    }
}
