# Correção da moldura do certificado

Data: 2026-06-22

## Alteração

- A primeira página do certificado passou a desenhar as três molduras diretamente com TCPDF.
- O HTML do template deixou de usar o wrapper externo de borda tripla.
- A segunda página permaneceu sem alteração de moldura.

## Validação

- `php -l app/Services/CertificadoService.php`
- Geração real de PDF validada com o certificado `DESRTKQ5NH`
- O PDF foi gerado com sucesso após a mudança
