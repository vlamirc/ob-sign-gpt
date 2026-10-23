<x-mail::message>
# Olá, {{ $signerName }}
Você recebeu um documento para assinatura: **{{ $documentTitle }}**.
<x-mail::button :url="$url">Abrir convite</x-mail::button>
O convite é individual e válido por 7 dias. Não compartilhe este link.
**Ambiente de MVP: utilize somente documentos de teste.**
</x-mail::message>
