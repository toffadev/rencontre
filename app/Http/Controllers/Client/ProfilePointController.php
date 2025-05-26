<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use App\Models\ProfilePointTransaction;
use App\Services\ProfilePointService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Inertia\Inertia;

class ProfilePointController extends Controller
{
    protected $profilePointService;

    public function __construct(ProfilePointService $profilePointService)
    {
        $this->profilePointService = $profilePointService;
    }

    /**
     * Crée une session de paiement Stripe pour acheter des points pour un profil
     */
    public function createCheckoutSession(Request $request)
    {
        try {
            $request->validate([
                'profile_id' => 'required|exists:profiles,id',
                'pack' => 'required|string|in:100,500,1000'
            ]);

            $profile = Profile::findOrFail($request->profile_id);

            $pointsPacks = [
                '100' => ['price' => 2.99, 'points' => 100],
                '500' => ['price' => 9.99, 'points' => 500],
                '1000' => ['price' => 16.99, 'points' => 1000]
            ];

            $pack = $pointsPacks[$request->pack];

            Log::info('Création d\'une session de paiement Stripe pour un profil', [
                'client_id' => Auth::id(),
                'profile_id' => $profile->id,
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
                            'name' => "{$pack['points']} Points pour {$profile->name}",
                            'description' => "Pack de {$pack['points']} points pour {$profile->name}"
                        ],
                        'unit_amount' => (int)($pack['price'] * 100)
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => route('profile.points.success') . '?session_id={CHECKOUT_SESSION_ID}&points=' . $pack['points'] . '&profile_id=' . $profile->id,
                'cancel_url' => route('client.profile.points', ['profile' => $profile->id]),
                'metadata' => [
                    'points_amount' => $pack['points'],
                    'client_id' => Auth::id(),
                    'profile_id' => $profile->id
                ]
            ]);

            // Créer la transaction en attente
            $transaction = $this->profilePointService->createProfilePointTransaction(
                Auth::user(),
                $profile,
                $pack['points'],
                [
                    'money_amount' => $pack['price'],
                    'stripe_session_id' => $session->id
                ]
            );

            if (!$transaction) {
                throw new \Exception('Impossible de créer la transaction');
            }

            return response()->json([
                'sessionId' => $session->id
            ]);
        } catch (ApiErrorException $e) {
            Log::error('Erreur Stripe lors de la création de la session pour le profil', [
                'error' => $e->getMessage(),
                'code' => $e->getStripeCode()
            ]);
            return response()->json([
                'error' => 'Une erreur est survenue avec Stripe: ' . $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la création de la session de paiement pour le profil', [
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

            // Trouver la transaction correspondante
            $transaction = ProfilePointTransaction::where('stripe_session_id', $session->id)
                ->where('status', 'pending')
                ->first();

            if ($transaction) {
                // Mettre à jour le payment_intent_id
                $transaction->stripe_payment_id = $session->payment_intent;
                $transaction->save();

                // Traiter la transaction
                $this->profilePointService->processSuccessfulTransaction($transaction);
            }
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Page de succès après paiement
     */
    public function success(Request $request)
    {
        if (!$request->session_id || !$request->profile_id) {
            return redirect()->route('client.home');
        }

        try {
            // Vérifier si la transaction existe et est complétée
            $transaction = ProfilePointTransaction::where('stripe_session_id', $request->session_id)
                ->where('profile_id', $request->profile_id)
                ->where('client_id', Auth::id())
                ->first();

            if ($transaction && $transaction->status === 'completed') {
                return redirect()->route('client.profile.points', ['profile' => $request->profile_id])
                    ->with('success', "Votre achat de {$transaction->points_amount} points pour {$transaction->profile->name} a été effectué avec succès !");
            }

            // Si la transaction existe mais n'est pas encore complétée
            if ($transaction) {
                Stripe::setApiKey(config('services.stripe.secret'));
                $session = Session::retrieve($request->session_id);

                if ($session->payment_status === 'paid') {
                    // Mettre à jour le payment_intent_id si nécessaire
                    if (!$transaction->stripe_payment_id) {
                        $transaction->stripe_payment_id = $session->payment_intent;
                        $transaction->save();
                    }

                    // Traiter la transaction
                    if ($this->profilePointService->processSuccessfulTransaction($transaction)) {
                        return redirect()->route('client.profile.points', ['profile' => $request->profile_id])
                            ->with('success', "Votre achat de {$transaction->points_amount} points pour {$transaction->profile->name} a été effectué avec succès !");
                    }
                }
            }

            return redirect()->route('client.profile.points', ['profile' => $request->profile_id])
                ->with('info', 'Votre paiement est en cours de traitement.');
        } catch (\Exception $e) {
            Log::error('Erreur lors du traitement du succès du paiement pour le profil', [
                'error' => $e->getMessage(),
                'session_id' => $request->session_id,
                'profile_id' => $request->profile_id
            ]);

            return redirect()->route('client.profile.points', ['profile' => $request->profile_id])
                ->with('error', 'Une erreur est survenue lors de la validation de votre paiement.');
        }
    }

    /**
     * Récupère l'historique des transactions de points pour un profil
     */
    public function getProfileTransactionHistory(Profile $profile)
    {
        return response()->json([
            'transactions' => $this->profilePointService->getProfileTransactionHistory($profile)
        ]);
    }

    /**
     * Récupère l'historique des transactions de points pour le client authentifié
     */
    public function getClientTransactionHistory()
    {
        return response()->json([
            'transactions' => $this->profilePointService->getClientTransactionHistory(Auth::user())
        ]);
    }

    public function index()
    {
        $user = Auth::user();
        return Inertia::render('Profile/Points', [
            'profile' => $user,
            'auth' => [
                'user' => $user
            ],
            'stripeKey' => config('services.stripe.key')
        ]);
    }
}
