<?php

namespace App\Http\Controllers;

use App\Models\Plano;
use App\Models\PlanoSelecionado;
use App\Services\MercadoPagoClient;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AssinaturaController extends Controller
{
    public function __construct(private MercadoPagoClient $client) {}

    /**
     * Inicia a assinatura de um plano pago - cria a preapproval no Mercado
     * Pago e devolve a URL de checkout hospedado para onde o React
     * redireciona o usuário. O plano só fica ativo (status=1) quando o
     * webhook confirmar o pagamento (POST /webhooks/mercadopago) - aqui a
     * seleção nasce pendente (status=0).
     */
    public function checkout(Request $request)
    {
        $data = $request->validate(['name_plano' => ['required', 'string']]);

        $plano = Plano::where('name_plano', $data['name_plano'])->first();

        if (! $plano) {
            return response()->json(['message' => 'Plano não encontrado.'], 404);
        }

        if ((float) $plano->valor <= 0) {
            return response()->json(['message' => 'Este plano não requer assinatura paga.'], 422);
        }

        $user = $request->user();
        $backUrl = rtrim(config('app.frontend_url', config('app.url')), '/').'/payment/status';

        $assinatura = $this->client->criarAssinatura($user, $plano, $backUrl);

        PlanoSelecionado::create([
            'id_usuario' => $user->id,
            'id_plano' => $plano->id,
            'status' => 0,
            'mp_subscription_id' => $assinatura['id'] ?? null,
        ]);

        return response()->json(['checkout_url' => $assinatura['init_point'] ?? null], 201);
    }

    /**
     * Rota pública (fora de auth:sanctum - o Mercado Pago chama direto,
     * sem token de usuário) - autenticada por assinatura HMAC do payload
     * via MERCADOPAGO_WEBHOOK_SECRET, nunca por Sanctum.
     *
     * Nunca confia cegamente no payload recebido: busca o estado real da
     * assinatura via MercadoPagoClient::buscarAssinatura antes de agir.
     */
    public function webhook(Request $request)
    {
        $this->assertAssinaturaValida($request);

        $mpSubscriptionId = $request->input('data.id') ?? $request->input('id');

        if (! $mpSubscriptionId) {
            return response()->json(['message' => 'Payload inválido.'], 422);
        }

        $planoSelecionado = PlanoSelecionado::where('mp_subscription_id', $mpSubscriptionId)->first();

        if (! $planoSelecionado) {
            // Pode ser notificação de outro tipo de recurso (pagamento
            // avulso, etc.) que este endpoint não trata - 200 evita que o
            // Mercado Pago fique reentregando indefinidamente.
            return response()->json(['message' => 'Assinatura não reconhecida.'], 200);
        }

        $assinaturaReal = $this->client->buscarAssinatura((string) $mpSubscriptionId);
        $status = $assinaturaReal['status'] ?? null;

        if ($status === 'authorized') {
            DB::transaction(function () use ($planoSelecionado, $assinaturaReal) {
                PlanoSelecionado::where('id_usuario', $planoSelecionado->id_usuario)
                    ->where('status', 1)
                    ->update(['status' => 0]);

                // Nome exato do campo de próxima cobrança não confirmado
                // contra a documentação real do Mercado Pago (sem
                // credenciais para testar de ponta a ponta - ver relatório
                // da tarefa) - +1 mês é seguro dado auto_recurring mensal
                // fixo, e é sobrescrito no próximo webhook de qualquer forma.
                $proximaCobranca = $assinaturaReal['auto_recurring']['next_payment_date']
                    ?? $assinaturaReal['next_payment_date']
                    ?? Carbon::now()->addMonth();

                $planoSelecionado->update([
                    'status' => 1,
                    'expira_em' => $proximaCobranca,
                ]);
            });
        }

        // cancelled/paused: NÃO desativa agora - expira_em já gravado
        // continua valendo até o fim do período pago (decisão de produto:
        // cancelamento mantém acesso até o fim do ciclo já pago).

        return response()->json(['ok' => true]);
    }

    /**
     * Validação de assinatura do webhook seguindo o esquema documentado
     * do Mercado Pago (header x-signature com ts/v1, HMAC-SHA256 do
     * manifest "id:{data_id};request-id:{x-request-id};ts:{ts};") - não
     * verificado byte-a-byte contra uma chamada real (sem credenciais de
     * teste); revisar contra a documentação atual do Mercado Pago antes
     * de operar em produção.
     */
    private function assertAssinaturaValida(Request $request): void
    {
        $secret = config('services.mercadopago.webhook_secret');

        if (! $secret) {
            return;
        }

        $signatureHeader = (string) $request->header('x-signature', '');
        $requestId = (string) $request->header('x-request-id', '');
        $dataId = $request->query('data_id') ?? $request->input('data.id');

        parse_str(str_replace(',', '&', $signatureHeader), $parts);
        $ts = $parts['ts'] ?? null;
        $v1 = $parts['v1'] ?? null;

        if (! $ts || ! $v1) {
            throw new HttpException(401, 'Assinatura do webhook ausente ou malformada.');
        }

        $manifest = "id:{$dataId};request-id:{$requestId};ts:{$ts};";
        $esperado = hash_hmac('sha256', $manifest, $secret);

        if (! hash_equals($esperado, (string) $v1)) {
            throw new HttpException(401, 'Assinatura do webhook inválida.');
        }
    }
}
