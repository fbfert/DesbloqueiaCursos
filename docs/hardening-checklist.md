# Hardening Checklist

## Problemas encontrados

1. `PedidoService` permitia finalizar checkout em qualquer estado do pedido.
2. `PedidoService` permitia anexar participantes fora do fluxo operacional do checkout.
3. `ComprovantePixService` aceitava upload sem checar o estado operacional do pedido.
4. `CertificadoService` dependia apenas das rotas para restringir emissoes e alteracoes.
5. O escopo do professor ja estava separado no dashboard e nas areas internas, mas precisava de validacao final em service para evitar acesso por troca de ids.

## Correcoes aplicadas

1. Adicionei validacao de estado em `PedidoService` para:
   - bloquear finalizacao em pedidos ja pagos, aprovados, cancelados ou reembolsados;
   - impedir inclusao de participantes apos o checkout ter sido consolidado.
2. Adicionei validacao de estado em `ComprovantePixService` para permitir upload apenas nos estados operacionais do fluxo.
3. Endureci `CertificadoService` com checagem direta de permissao para emissao, reemissao, cancelamento e revogacao.
4. Mantive o CSRF centralizado no roteamento de `POST` e a injecao de token no renderer de views.
5. Mantive registro de auditoria e log de negacao em middleware e services sensiveis.

## Pendencias restantes

1. Rate limiting por IP/login ainda pode ser reforcado em camada propria.
2. Headers de seguranca HTTP podem ser ampliados no front controller ou no servidor web.
3. Downloads privados ainda dependem de controle por rota e service, sem links assinados temporarios.
4. MFA/2FA para perfis administrativos continua fora do escopo atual.
5. Monitoramento e alertas de abuso ainda nao estao automatizados.
