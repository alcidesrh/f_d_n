<?php

namespace App\Services;

use App\Entity\Empresa;
use App\Entity\Reservacion;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Crypto\DkimSigner;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\File;

class Mails {

    private ?Email   $email = null;

    public function __construct(private TranslatorInterface $translatorInterface, private Environment $environment, private MailerInterface $mailer, private Filesystem $filesystem, private RequestStack $requestStack, private EntityManagerInterface $entityManagerInterface,  private $private_key, #[Autowire('%webmaster_email%')] private $webmaster_email,) {
    }

    public function reservacionEmail(Reservacion $reservacion, $to = null) {

        $to ??= $reservacion->getCliente()->getEmail();
        $this->email = (new Email())
            ->from($this->translatorInterface->trans('boleto') . '@transportesfuentedelnorte.com')
            ->to($to)
            ->priority(Email::PRIORITY_HIGH)
            ->subject($this->translatorInterface->trans('Boleto de Bus Transporte Fuente del Norte. Servicio de Bus. Guatemala.'))
            ->text($this->translatorInterface->trans('Boleto de Bus Transporte Fuente del Norte. Servicio de Bus. Guatemala.'));


        if ($factura_path = $reservacion->getFacturaPdfPath()) {
            $this->email->html($this->environment->render('email/factura.html.twig', ['reservacion' => $reservacion, 'nit_emisor' => Empresa::nit_emisor, 'boleto_path' => '/' . $reservacion->getFacturaPdfPath()]))->addPart(new DataPart(new File('/srv/app/public/' . $factura_path)));
        } else {
            $this->email->html($this->environment->render('email/factura.html.twig', ['reservacion' => $reservacion, 'nit_emisor' => Empresa::nit_emisor]));
        }

        if ($this->send()) {
            $reservacion->setEmailEnviado(true);

            $this->entityManagerInterface->persist($reservacion);
            $this->entityManagerInterface->flush();;

            return true;
        }
        return false;
    }

    public function notificacion($subject,  $msg = null) {

        $this->email = (new Email())
            ->from('anular@transportesfuentedelnorte.com')
            ->to($this->webmaster_email)
            ->priority(Email::PRIORITY_HIGH)
            ->subject($subject)
            ->text($msg ?? $subject);

        return $this->send();
    }

    public function venta($qtz,  $usd) {

        $this->email = (new Email())
            ->from('venta@transportesfuentedelnorte.com')
            ->to('edwinmendoza58@gmail.com', 'pablomendozafdn@hotmail.com', $this->webmaster_email)
            ->priority(Email::PRIORITY_HIGH)
            ->subject('Página Web Ventas')
            ->html($this->environment->render('email/venta.html.twig', ['qtz' => $qtz, 'usd' => $usd]));

        return $this->send();
    }

    public function contacto($msg) {

        $this->email = (new Email())
            ->from('contacto@transportesfuentedelnorte.com')
            ->to($this->webmaster_email)
            ->priority(Email::PRIORITY_NORMAL)
            ->subject('Contacto desde la pagina')
            ->text($msg);

        return $this->send();
    }

    public function send() {
        try {
            $email = $this->email;

            // if (\in_array('alcidesrh@gmail.com', \array_map(fn ($i) => $i['address']->getAddress(), $email->getTo()))) {
            //     if ($this->filesystem->exists($this->private_key)) {
            //         $signer = new DkimSigner('file://' . Path::canonicalize($this->private_key),  'transportesfuentedelnorte.com', 'ohmysmtp._domainkey');
            //         $email = $signer->sign($email);
            //     }
            // }
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            throw new \Exception($e->getMessage());
        }
        return true;
    }
}
