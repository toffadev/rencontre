<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\PointService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Inertia\Inertia;
use App\Models\PointTransaction;

class PointController extends Controller
{
    protected $pointService;

    public function __construct(PointService $pointService)
    {
        $this->pointService = $pointService;
    }

    /**
     * Obtient les données des points pour la page de profil
     */
    public function getPointsData()
    {
        $user = Auth::user();

        return response()->json([
            'points' => $user->points,
            'transactions' => $this->pointService->getTransactionHistory($user),
            'consumptions' => $this->pointService->getConsumptionHistory($user)
        ]);
    }

    /**
     * Crée une session de paiement Stripe
     */
    public function createCheckoutSession(Request $request)
    {
        try {
            $request->validate([
                'pack' => 'required|string|in:100,500,1000'
            ]);

            $pointsPacks = [
                '100' => ['price' => 2.99, 'points' => 100],
                '500' => ['price' => 9.99, 'points' => 500],
                '1000' => ['price' => 16.99, 'points' => 1000]
            ];

            $pack = $pointsPacks[$request->pack];

            // Log pour le débogage
            Log::info('Création d\'une session de paiement Stripe', [
                'user_id' => Auth::id(),
                'pack' => $request->pack,
                'amount' => $pack['price']
            ]);

            Stripe::setApiKey(config('services.stripe.secret'));

            $session = Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'eur',
                        'product_data' => [
                            'name' => "{$pack['points']} Points",
                            'description' => "Pack de {$pack['points']} points pour envoyer des messages"
                        ],
                        'unit_amount' => (int)($pack['price'] * 100) // Stripe utilise les centimes
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => route('client.points.success') . '?session_id={CHECKOUT_SESSION_ID}&points=' . $pack['points'],
                'cancel_url' => route('profile'),
                'metadata' => [
                    'points_amount' => $pack['points'],
                    'user_id' => Auth::id()
                ]
            ]);

            Log::info('Session Stripe créée avec succès', [
                'session_id' => $session->id
            ]);

            return response()->json([
                'sessionId' => $session->id
            ]);
        } catch (ApiErrorException $e) {
            Log::error('Erreur Stripe lors de la création de la session', [
                'error' => $e->getMessage(),
                'code' => $e->getStripeCode()
            ]);
            return response()->json([
                'error' => 'Une erreur est survenue avec Stripe: ' . $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la création de la session de paiement', [
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'error' => 'Une erreur est survenue lors de la création de la session de paiement'
            ], 500);
        }
    }

    /**
     * Gère le webhook Stripe pour les paiements réussis
     */
    public function handleWebhook(Request $request)
    {
        // Si le webhook secret n'est pas configuré, on ignore cette fonctionnalité
        if (!config('services.stripe.webhook_secret')) {
            Log::warning('Webhook Stripe ignoré car le secret n\'est pas configuré');
            return response()->json(['status' => 'ignored']);
        }

        $payload = $request->getContent();
        $sig_header = $request->header('Stripe-Signature');
        $endpoint_secret = config('services.stripe.webhook_secret');

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $sig_header,
                $endpoint_secret
            );
        } catch (\UnexpectedValueException $e) {
            return response()->json(['error' => 'Invalid payload'], 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;
            $this->processSuccessfulPayment($session);
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Page de succès après paiement
     */
    public function success(Request $request)
    {
        if (!$request->session_id) {
            return redirect()->route('profile');
        }

        try {
            // Vérifier si la transaction existe déjà
            $transaction = PointTransaction::where('stripe_session_id', $request->session_id)
                ->where('user_id', Auth::id())
                ->first();

            // Si la transaction n'existe pas encore (webhook pas encore reçu), on la crée
            if (!$transaction && $request->points) {
                Stripe::setApiKey(config('services.stripe.secret'));
                $session = Session::retrieve($request->session_id);

                if ($session->payment_status === 'paid') {
                    $this->processSuccessfulPayment($session);
                    $transaction = PointTransaction::where('stripe_session_id', $request->session_id)
                        ->where('user_id', Auth::id())
                        ->first();
                }
            }

            if ($transaction) {
                return redirect()->route('profile')
                    ->with('success', "Votre achat de {$transaction->points_amount} points a été effectué avec succès !");
            }

            return redirect()->route('profile')
                ->with('info', 'Votre paiement est en cours de traitement.');
        } catch (\Exception $e) {
            Log::error('Erreur lors du traitement du succès du paiement', [
                'error' => $e->getMessage(),
                'session_id' => $request->session_id
            ]);

            return redirect()->route('profile')
                ->with('error', 'Une erreur est survenue lors de la validation de votre paiement.');
        }
    }

    /**
     * Traite un paiement réussi
     */
    private function processSuccessfulPayment($session)
    {
        $userId = $session->metadata->user_id;
        $pointsAmount = $session->metadata->points_amount;

        $user = \App\Models\User::find($userId);
        if ($user) {
            $this->pointService->addPoints($user, $pointsAmount, [
                'type' => 'purchase',
                'money_amount' => $session->amount_total / 100,
                'stripe_payment_id' => $session->payment_intent,
                'stripe_session_id' => $session->id,
                'description' => "Achat de {$pointsAmount} points"
            ]);
        }
    }
}
