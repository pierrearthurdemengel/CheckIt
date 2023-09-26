<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Exception\BreachDirectoryException;
use App\Form\EmailCheckType;
use Symfony\Component\Routing\Annotation\Route;

class BreachDirectoryController extends AbstractController
{
    private $client;
    private $apiKey;

    public function __construct(HttpClientInterface $client, string $apiKey)
    {
        $this->client = $client;
        $this->apiKey = $apiKey;
    }

    #[Route('/', name: 'homepage')]
    public function homepage(Request $request): Response
    {
        $form = $this->createForm(EmailCheckType::class);
        
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $email = $form->get('email')->getData();
            return $this->redirectToRoute('results', ['email' => $email]);
        }
        
        return $this->render('breach_directory/homepage.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/results', name: 'results')]
    public function checkEmail(Request $request): Response
    {
        $email = $request->query->get('email');

        try {
            $response = $this->client->request('GET', 'https://breachdirectory.p.rapidapi.com/', [
                'headers' => [
                    'X-RapidAPI-Key' => $this->apiKey,
                    'X-RapidAPI-Host' => 'breachdirectory.p.rapidapi.com',
                ],
                'query' => [
                    'func' => 'auto',
                    'term' => $email,
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $content = $response->toArray();
        } catch (BreachDirectoryException $e) {
            return $this->render('breach_directory/error.html.twig', ['error' => $e->getMessage()]);
        }

        return $this->render('breach_directory/results.html.twig', [
            'status_code' => $statusCode,
            'content' => $content,
        ]);
    }
}
