# O gabarito era identificável pelo tamanho — correção nos cinco simulados

Data: 2026-08-16
Alcance: quizzes 24, 25, 26, 27 e 28 (cursos 118 a 122), 1.202 questões objetivas

## O defeito

Em **91% das questões objetivas dos cinco simulados a alternativa correta era a
mais longa**, excedendo as demais por uma média de 52 a 155 caracteres conforme
o componente. O esperado por acaso, com cinco alternativas, é 20%.

Um aluno que não estudasse nada e marcasse sempre a alternativa mais comprida
acertaria perto de 90% das objetivas em Pedagogia, Letras, Geografia e História.

O simulado deixava de medir o que se propõe a medir, e pior: treinava o aluno
numa heurística que a prova real não recompensa.

### Como escapou de todas as verificações anteriores

- **o embaralhamento não resolve.** `embaralhar_alternativas = 1` nos cinco
  quizzes e o `randomizer->embaralhar` roda de fato no snapshot, mas ele
  embaralha a ORDEM. O comprimento viaja junto com o texto;
- **a conferência adversarial não pega.** Ela avalia se o conteúdo está certo e
  se o gabarito se sustenta — não a forma. Um banco pode ter 100% de gabaritos
  corretos e ainda entregá-los pelo tamanho;
- **as varreduras mecânicas não procuravam isso.** Elas checavam HTML, dado
  inventado, determinismo, escala. Nenhuma olhava a distribuição de comprimento.

O achado veio do agente conferente do curso 122, que notou o padrão num bloco e
o reportou como "defeito sistêmico". A medição posterior mostrou que valia para
os cinco cursos, e não só para aquele bloco.

## A origem

É o vício clássico de banco de questões escrito por quem domina o assunto: a
alternativa correta precisa ser precisa e completa, então ganha orações. Os
distratores são frases curtas de negação — "Ignorar o problema.", "Reaplicar a
prova." Ninguém escreve isso de propósito; sai assim.

## O método de correção

Reescrever os **distratores**, nunca a alternativa correta.

A trava estrutural: **os agentes não recebiam a alternativa correta e não a
devolviam**. Trabalhavam só com os quatro distratores. Isso torna
matematicamente impossível a correção alterar qual é o gabarito — o risco óbvio
ao mexer em 842 questões de uma vez. O script de gravação recusaria escrever se
qualquer id do lote fosse de alternativa correta; nenhum era.

Os distratores não foram inflados com redundância. A instrução exigia crescer
**explicitando a concepção equivocada e o encaminhamento que decorreria dela**:

> ruim: "Reaplicar a prova, pois a turma estava nervosa e o resultado não
> reflete o aprendizado, sendo necessário repetir para confirmar."
>
> bom: "Reaplicar a mesma avaliação em outra data, tratando o resultado como
> efeito do nervosismo da turma, e registrar a segunda nota como a válida para
> o bimestre."

## A lição: alvo aproximado falha quando o buraco é grande

A primeira passada pediu cada distrator entre **85% e 115%** do tamanho da
correta. O resultado dependeu inteiramente do tamanho do problema original:

| Componente | Gap original | Falhas após 1ª passada |
|---|---|---|
| Matemática | +31 car | 1% |
| Pedagogia | +55 | 0% |
| FGD | +52 | 0% |
| Geografia | +95 | 23% |
| Letras | +122 | 24% |
| História | +155 | **41%** |

Onde o gap era de ~50 caracteres, uma passada resolveu completamente. Onde era
de 95 a 155, a faixa pedia um salto que os agentes não deram por inteiro:
subiram, mas pararam abaixo da correta.

A segunda passada, sobre as 134 questões que ainda falhavam, trocou o alvo
aproximado por uma **condição verificável**: em cada questão, pelo menos um
distrator com comprimento **maior ou igual** ao da correta. Isso força a margem
a zero ou negativa por construção, em vez de depender de calibragem. 134
questões, 134 aprovadas.

**Regra geral que fica:** quando a correção exige um salto grande, dê ao agente
um critério binário e verificável, não uma faixa. E avise no prompt que o
resultado será conferido por script — a diferença de rigor é perceptível.

## Resultado

| | Antes | Depois |
|---|---|---|
| Correta é a mais longa | 91% (1.093 de 1.202) | 39% (468) |
| **Mais longa por mais de 15 caracteres** | ~98% | **0** |
| Margem média | +52 a +155 car | +0 a +14 |

A segunda linha é a que importa. Não existe mais **uma única questão** nos cinco
bancos em que a correta ultrapasse todas as outras por mais de 15 caracteres.

Os 39% restantes são empates e diferenças de poucos caracteres entre
alternativas de 200 a 500 — imperceptíveis. Não se perseguiu os 20% do acaso de
propósito: seria otimizar a métrica em vez da propriedade, e forçar "a correta
nunca é a mais longa" criaria um padrão novo, igualmente explorável.

## Economia que vale registrar

O bloco de **Formação Geral Docente é idêntico nos cinco simulados** (assinatura
`26b44c3128d9` conferida). Corrigir 90 questões uma vez consertou o bloco de
pior caso — 100% das corretas eram as mais longas — em cinco provas, e em
qualquer curso futuro, já que todo novo curso copia o FGD daí.

A propagação casou por **texto antigo do distrator dentro da questão de mesmo
enunciado**, não por id, porque as cópias têm ids próprios. 360 distratores
atualizados em cada um dos cinco quizzes, zero sem casar.

## Verificação e segurança

- integridade das 752 + 90: zero erro de id, zero texto vazio, zero HTML;
- após gravar: **zero questões sem exatamente um gabarito** nos cinco simulados;
- backups: `backups/fgd-pre-equilibrio-20260816-103526.sql.gz` e
  `backups/componentes-pre-equilibrio-20260816-142007.sql.gz`.

## A checagem virou permanente

`scripts/verificar_banco_questoes.php` ganhou a verificação, para que um curso
novo não possa nascer com o mesmo defeito sem ninguém perceber — que foi
exatamente o que houve com os cinco primeiros.

O corte é a **margem perceptível**, não "ser a mais longa": acusa quando a
correta excede o maior distrator em mais de 15 caracteres. Até 25% das questões
do quiz é aviso; acima disso é problema, com a lista dos itens e a instrução de
corrigir pelos distratores.

**O detector foi testado contra o defeito, não só contra o banco limpo.** Uma
checagem que só sabe dizer "OK" não prova nada. Numa transação revertida, os
distratores de 40 questões do quiz 28 foram truncados em 40 caracteres: a
detecção passou de 0 para 40 e voltou a 0 após o rollback, com o banco real
intacto.

Os cinco simulados passam limpos.
