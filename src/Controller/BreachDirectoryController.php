<?php

namespace App\Controller;

use Psr\Log\LoggerInterface;
use App\FormType\EmailCheckType;
use Symfony\Component\Validator\Validation;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Validator\Constraints\Email as EmailConstraint;

class BreachDirectoryController extends AbstractController
{
    private $client;
    private $apiKey;
    private $logger;

    // Constructeur avec injection du client HTTP, de la clé API et du service de log
    public function __construct(HttpClientInterface $client, string $apiKey, LoggerInterface $logger)
    {
        $this->client = $client;
        $this->apiKey = $apiKey;
        $this->logger = $logger;
        $this->logger->info('Le contrôleur BreachDirectory a été initialisé.');
    }

    // Route pour la page d'accueil
    #[Route('/', name: 'app_homepage')]
    public function homepage(Request $request): Response
    {
        $this->logger->info('Accès à la page d\'accueil.');

        // Création et gestion du formulaire
        $form = $this->createForm(EmailCheckType::class);
        $form->handleRequest($request);
        $this->logger->debug('Le formulaire a été géré.');

        // Vérification de la soumission et de la validité du formulaire
        if ($form->isSubmitted() && $form->isValid()) {
            $email = $form->get('email')->getData();
            $this->logger->info("L'email soumis est : {$email}");
            
            // Redirection vers la page de résultats avec l'email encodé dans l'URL
            return $this->redirect($this->generateUrl('results') . '?email_check[email]=' . urlencode($email));
        }

        // Rendu de la page d'accueil avec le formulaire
        return $this->render('breach_directory/homepage.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    // Route pour la page de résultats après vérification de l'email
    #[Route('/results', name: 'results', methods: ["GET"])]
    public function checkEmail(Request $request): Response
    {
        $this->logger->info('Accès à la page de résultats.');

        // Récupération de l'email à partir des données de la requête
        $formData = $request->query->get('email_check');
        $email = $formData['email'] ?? null;

        // Si aucun email n'est fourni, on log une alerte
        if (empty($email)) {
            $this->logger->warning("Aucun email fourni pour la vérification.");
            return new Response('Aucun e-mail n\'a été fourni pour vérification.', Response::HTTP_BAD_REQUEST);
        }

        // Log de l'email en cours de vérification
        $this->logger->info("Vérification de l'email : {$email}");

        // Validation de l'email
        $validator = Validation::createValidator();
        $violations = $validator->validate($email, [new EmailConstraint()]);

        // Si des erreurs de validation sont présentes, on les log
        if (0 !== count($violations)) {
            foreach ($violations as $violation) {
                $this->logger->warning("Erreur de validation : " . $violation->getMessage());
            }
            return new Response('L\'adresse email n\'est pas valide.', Response::HTTP_BAD_REQUEST);
        }

        // Bloc try pour la gestion de la requête à l'API et des erreurs potentielles
        try {
            // Construction de l'URL pour la requête à l'API
            $url = 'https://haveibeenpwned.com/api/v3/breachedaccount/' . urlencode($email);
            $this->logger->info("URL de la requête : {$url}");

            // Définition des en-têtes de la requête HTTP
            $headers = [
                'hibp-api-key' => $this->apiKey,
                'user-agent' => 'CheckIt',
            ];
            $this->logger->info("En-têtes envoyés : " . json_encode($headers));

            // Envoi de la requête HTTP avec les en-têtes
            $response = $this->client->request('GET', $url, [
                'headers' => $headers,
            ]);

            // Log du code de statut de la réponse HTTP
            $statusCode = $response->getStatusCode();
            $this->logger->info("Code de statut de la réponse : {$statusCode}");

            // Gestion des réponses en fonction du code de statut
            if ($statusCode === 200) {
                $data = json_decode($response->getContent(), true);
                return $this->render('breach_directory/results.html.twig', [
                    'email' => $email,
                    'breaches' => $data,
                ]);
            } elseif ($statusCode === 404) {
                return $this->render('breach_directory/results.html.twig', [
                    'email' => $email,
                    'breaches' => [],
                ]);
            } else {
                $this->logger->error("Code de statut inattendu : {$statusCode}");
                return new Response('Une réponse inattendue a été reçue de l\'API Have I Been Pwned.', Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        } catch (\Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            $this->logger->error("ClientException avec le code de statut {$statusCode} : " . $e->getMessage());
            return new Response('Erreur lors de la communication avec l\'API Have I Been Pwned.', Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (\Exception $e) {
            $this->logger->critical("Exception générale : " . $e->getMessage());
            return new Response('Erreur lors de la communication avec l\'API Have I Been Pwned.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
