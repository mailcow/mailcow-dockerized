<?php

/*
 +-----------------------------------------------------------------------+
 | localization/<lang>/messages.inc                                      |
 |                                                                       |
 | Localization file of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2015, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 +-----------------------------------------------------------------------+

 For translation see https://www.transifex.com/projects/p/roundcube-webmail/resource/messages/
*/
$messages['errortitle']  = 'Dogodila se greška!';
$messages['loginfailed']  = 'Prijava neuspješna.';
$messages['cookiesdisabled'] = 'Vaš preglednik ne prihvaća kolačiće.';
$messages['sessionerror'] = 'Vaša sesija je nevaljala ili istekla.';
$messages['storageerror'] = 'Neuspješno povezivanje na IMAP poslužitelj.';
$messages['servererror'] = 'Greška poslužitelja!';
$messages['servererrormsg'] = 'Greška poslužitelja: $msg';
$messages['connerror'] = 'Neuspješna veza (Nemoguće doći do poslužitelja)!';
$messages['dberror'] = 'Greška baze podataka!';
$messages['requesttimedout'] = 'Zahtjev istekao';
$messages['errorreadonly'] = 'Nemoguće izvršiti operaciju. Mapa je samo za čitanje.';
$messages['errornoperm'] = 'Nemoguće izvršiti operaciju. Nije dozvoljen pristup.';
$messages['erroroverquota'] = 'Nemoguće izvršiti operaciju. Nema prostora na disku.';
$messages['erroroverquotadelete'] = 'Nema prostora na disku. Upotrijebite SHIFT+DEL za brisanje poruka.';
$messages['invalidrequest'] = 'Neispravan zahtjev! Podaci nisu spremljeni.';
$messages['invalidhost'] = 'Neispravno ime poslužitelja.';
$messages['nomessagesfound'] = 'Nema poruka u ovom sandučiću.';
$messages['loggedout'] = 'Uspješno ste se odjavili. Doviđenja!';
$messages['mailboxempty'] = 'Sandučuć je prazan';
$messages['nomessages'] = 'Nema poruka';
$messages['refreshing'] = 'Osvježavanje...';
$messages['loading'] = 'Učitavanje...';
$messages['uploading'] = 'Postavljanje datoteke...';
$messages['uploadingmany'] = 'Postavljanje datoteka...';
$messages['loadingdata'] = 'Učitavanje podataka...';
$messages['checkingmail'] = 'Provjera novih poruka u tijeku...';
$messages['sendingmessage'] = 'Slanje poruke...';
$messages['messagesent'] = 'Poruka uspješno poslana.';
$messages['savingmessage'] = 'Spremanje poruke...';
$messages['messagesaved'] = 'Poruka spremljena u mapu Predlošci.';
$messages['successfullysaved'] = 'Uspješno spremljeno.';
$messages['savingresponse'] = 'Spremanje teksta odgovora...';
$messages['deleteresponseconfirm'] = 'Jeste li sigurni da želite obrisati tekst ovog odgovora?';
$messages['addedsuccessfully'] = 'Kontakt uspješno dodan u imenik.';
$messages['contactnameexists'] = 'Kontakt istog imena već postoji.';
$messages['blockedimages'] = 'Zbog zaštite privatnosti, slike sa interneta su blokirane u ovoj poruci.';
$messages['encryptedmessage'] = 'Ova poruka je šifrirana i ne može se prikazati.';
$messages['nocontactsfound'] = 'Imenik je prazan.';
$messages['contactnotfound'] = 'Traženi kontakt nije pronađen.';
$messages['contactsearchonly'] = 'Upišite neke pojmove za pretragu imenika';
$messages['sendingfailed'] = 'Slanje poruke nije uspjelo.';
$messages['senttooquickly'] = 'Molimo sačekajte $sec sek. prije slanja ove poruke.';
$messages['errorsavingsent'] = 'Greška pri spremanju poslane poruke.';
$messages['errorsaving'] = 'Greška pri spremanju.';
$messages['errormoving'] = 'Greška pri premještanju poruke(a).';
$messages['errorcopying'] = 'Greška pri kopiranju poruke(a).';
$messages['errordeleting'] = 'Greška pri brisanju poruke(a).';
$messages['errormarking'] = 'Greška pri označavanju poruke(a).';
$messages['deletecontactconfirm']  = 'Jeste li sigurni da želite obrisati odabrani kontakt(e)?';
$messages['deletegroupconfirm']  = 'Jeste li sigurni da želite obrisati odabranu grupu?';
$messages['deletemessagesconfirm'] = 'Jeste li sigurni da želite obrisati odabranu poruku(e)?';
$messages['deletefolderconfirm']  = 'Jeste li sigurni da želite obrisati mapu?';
$messages['purgefolderconfirm']  = 'Jeste li sigurni da želite obrisati sve poruke u mapi?';
$messages['contactdeleting'] = 'Brisanje kontak(a)ta...';
$messages['groupdeleting'] = 'Brisanje grupe...';
$messages['folderdeleting'] = 'Brisanje mape...';
$messages['foldermoving'] = 'Premještanje mape...';
$messages['foldersubscribing'] = 'Pretplaćivanje na mapu...';
$messages['folderunsubscribing'] = 'Poništavanje pretplate na mapu...';
$messages['formincomplete'] = 'Obrazac nije u cjelosti popunjen.';
$messages['noemailwarning'] = 'Unesite ispravnu e-mail adresu.';
$messages['nonamewarning']  = 'Unesite ime.';
$messages['nopagesizewarning'] = 'Unesite veličinu stranice.';
$messages['norecipientwarning'] = 'Unesite barem jednog primatelja.';
$messages['nosubjectwarning']  = 'Polje \'Naslov\' je prazno. Želite li unijeti naslov?';
$messages['nobodywarning'] = 'Želite li poslati poruku bez teksta?';
$messages['notsentwarning'] = 'Poruka nije poslana. Želite li odbaciti ovu poruku?';
$messages['restoresavedcomposedata'] = 'Pronađena je započeta, ali neposlana poruka .\n\nNaslov: $subject\nSnimljeno: $date\n\nŽelite li je prikazati?';
$messages['noldapserver'] = 'Odaberite LDAP poslužitelj za pretragu.';
$messages['nosearchname'] = 'Unesite ime ili e-mail adresu.';
$messages['notuploadedwarning'] = 'Svi privitci još nisu postavljeni na poslužitelj. Molim pričekajte ili zaustavite postavljanje.';
$messages['searchsuccessful'] = 'Pronađeno $nr poruka.';
$messages['contactsearchsuccessful'] = 'Pronađeno $nr kontakata.';
$messages['searchnomatch'] = 'Pretraga nije vratila nijedan rezultat.';
$messages['searching'] = 'Pretraživanje u tijeku...';
$messages['checking'] = 'Provjera u tijeku...';
$messages['nospellerrors'] = 'Nije pronađena nijedna pravopisna greška.';
$messages['folderdeleted'] = 'Mapa uspješno obrisana.';
$messages['foldersubscribed'] = 'Mapa uspješno pretplaćena.';
$messages['folderunsubscribed'] = 'Pretplata na mapu uspješno poništena.';
$messages['folderpurged'] = 'Mapa uspješno ispražnjena.';
$messages['folderexpunged'] = 'Mapa uspješno sažeta.';
$messages['deletedsuccessfully'] = 'Uspješno obrisano.';
$messages['converting'] = 'Uklanjanje formatiranja poruke...';
$messages['messageopenerror'] = 'Učitavanje poruke s poslužitelja nije uspjelo.';
$messages['fileuploaderror'] = 'Postavljanje datoteke nije uspjelo.';
$messages['filesizeerror'] = 'Postavljena datoteka prelazi maksimalna veličinu od $size.';
$messages['copysuccess'] = 'Uspješno kopirano $n kontakata.';
$messages['movesuccess'] = 'Uspješno premješteno $n kontakata.';
$messages['copyerror'] = 'Kopiranje kontakata nije uspjelo.';
$messages['moveerror'] = 'Premještanje kontakata nije uspjelo.';
$messages['sourceisreadonly'] = 'Ovaj izvor adresa je samo za čitanje.';
$messages['errorsavingcontact'] = 'Spremanje adrese kontakta nije uspjelo.';
$messages['movingmessage'] = 'Premještanje poruke(a)...';
$messages['copyingmessage'] = 'Kopiranje poruke(a)...';
$messages['copyingcontact'] = 'Kopiranje kontak(a)ta...';
$messages['movingcontact'] = 'Premještanje kontakata...';
$messages['deletingmessage'] = 'Brisanje poruke(a)...';
$messages['markingmessage'] = 'Označavanje poruke(a)...';
$messages['addingmember'] = 'Dodavanje kontak(a)ta u grupu...';
$messages['removingmember'] = 'Brisanje kontak(a)ta iz grupe...';
$messages['receiptsent'] = 'Potvrda o primitku uspješno poslana.';
$messages['errorsendingreceipt'] = 'Slanje potvrde o primitku nije uspjelo.';
$messages['deleteidentityconfirm'] = 'Jeste li sigurni da želite obrisati ovaj identitet?';
$messages['nodeletelastidentity'] = 'Jedini preostali identitet nije moguće obrisati.';
$messages['forbiddencharacter'] = 'Naziv mape sadrži zabranjene znakove.';
$messages['selectimportfile'] = 'Odaberite datoteku za postavljanje.';
$messages['addresswriterror'] = 'U odabrani imenik nije omogućeno pisanje.';
$messages['contactaddedtogroup'] = 'Kontakti uspješno dodani u grupu.';
$messages['contactremovedfromgroup'] = 'Kontakti uspješno obrisani iz grupe.';
$messages['nogroupassignmentschanged'] = 'Nema promjene u zadacima grupa.';
$messages['importwait'] = 'Uvoz, molim pričekajte...';
$messages['importformaterror'] = 'Uvoz nije uspio! Postavljena datoteka nije ispravnog formata za uvoz.';
$messages['importconfirm'] = '<b>Uspješno uvezeno $inserted kontakta</b>';
$messages['importconfirmskipped'] = '<b>Preskočeno $skipped postojećih unosa</b>';
$messages['importmessagesuccess'] = 'Uspješno uvezeno $nr poruka';
$messages['importmessageerror'] = 'Uvoz neuspješan! Datoteka nije u formatu poruke ili sandučića';
$messages['opnotpermitted'] = 'Operacija nije dozvoljena!';
$messages['httpreceivedencrypterror'] = 'Dogodila se fatalna greška u konfiguraciji. Odmah kontaktirajte administratora. <b>Vaša poruka se ne može poslati.</b>';
$messages['smtpconnerror'] = 'SMTP greška ($code): Povezivanje na poslužitelj nije uspjelo.';
$messages['smtpautherror'] = 'SMTP greška ($code): Autentikacija nije uspjela.';
$messages['smtpfromerror'] = 'SMTP greška ($code): Postavljanje pošiljatelja "$from" ($msg) nije uspjelo.';
$messages['smtptoerror'] = 'SMTP greška ($code): Dodavanje primatelja "$to" ($msg) nije uspjelo.';
$messages['smtprecipientserror'] = 'SMTP greška: Nije moguće pročitati listu primatelja.';
$messages['smtperror'] = 'SMTP greška: $msg';
$messages['toomanyrecipients'] = 'Previše primatelja. Smanjite broj primatelja na $max.';
$messages['maxgroupmembersreached'] = 'Broj članova grupe prelazi maksimalni broj od $max.';
$messages['internalerror'] = 'Dogodila se interna greška. Molimo pokušajte ponovno.';
$messages['contactdelerror'] = 'Kontakti ne mogu biti obrisani.';
$messages['contactdeleted'] = 'Kontakti uspješno obrisani.';
$messages['contactrestoreerror'] = 'Ne mogu vratiti obrisan(e) kontakt(e).';
$messages['contactrestored'] = 'Kontakt(i) uspješno vraćeni.';
$messages['groupdeleted'] = 'Grupa uspješno obrisana.';
$messages['grouprenamed'] = 'Grupa uspješno preimenovana.';
$messages['groupcreated'] = 'Grupa uspješno stvorena.';
$messages['savedsearchdeleted'] = 'Pohranjena pretraga uspješno obrisana.';
$messages['savedsearchdeleteerror'] = 'Ne mogu obrisati pohranjenu pretragu.';
$messages['savedsearchcreated'] = 'Pohranjena pretraga uspješno stvorena.';
$messages['savedsearchcreateerror'] = 'Ne mogu stvoriti pohranjenu pretragu.';
$messages['messagedeleted'] = 'Poruke uspješno obrisane.';
$messages['messagemoved'] = 'Poruke uspješno premještene.';
$messages['messagecopied'] = 'Poruke uspješno kopirane.';
$messages['messagemarked'] = 'Poruke uspješno označene.';
$messages['autocompletechars'] = 'Unesite barem $min znakova za auto-dopunjavanje.';
$messages['autocompletemore'] = 'Više podudarajućih zapisa pronađeno. Molim upišite još znakova.';
$messages['namecannotbeempty'] = 'Ime ne može biti prazno.';
$messages['nametoolong'] = 'Ime je predugačko.';
$messages['folderupdated'] = 'Mapa uspješno ažurirana.';
$messages['foldercreated'] = 'Mapa uspješno stvorena.';
$messages['invalidimageformat'] = 'Format slike nije ispravan.';
$messages['mispellingsfound'] = 'Poruka sadrži pravopisne greške.';
$messages['parentnotwritable'] = 'Nije moguće stvoriti/pomaknuti mapu u odabranoj mapi. Nemate pravo pristupa.';
$messages['messagetoobig'] = 'Dio poruke je prevelik za obradu.';
$messages['attachmentvalidationerror'] = 'UPOZORENJE! Ovaj privitak je sumnjiv jer vrsta privitka ne odgovara vrsti deklariranoj u poruci. Ako ne vjerujete pošiljatelju, ne biste trebali otvarati privitak u pregledniku jer može isti može sadržavati zloćudni kod.<br/><br/><em>Očekivano: $expected; pronađeno: $detected</em>';
$messages['noscriptwarning'] = 'UPOZORENJE: Ovaj webmail servis zahtijeva Javascript!  Kako biste ga mogli koristiti, molimo omogućite Javascript u postavkama svog preglednika.';
