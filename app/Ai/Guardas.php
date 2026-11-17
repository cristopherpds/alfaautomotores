<?php

namespace App\Ai;

/**
 * Las reglas que no se le dejan al modelo, portadas de nea-agent.
 *
 * Contar entre turnos y reconocer un pedido de humano es justo lo que un LLM
 * hace de forma poco confiable: a veces cuenta y a veces sigue vendiendo.
 * Acá es determinista y el modelo sólo pone la redacción.
 */
class Guardas
{
    /**
     * Mensajes hostiles seguidos que cierran la conversación con una línea
     * digna y la pasan al equipo.
     */
    public const RACHA_HOSTIL = 3;

    /**
     * Agresión dirigida y desprecio, en registro rioplatense. Conservador a
     * propósito: «la puta madre» o «qué garrón» solos no cuentan, así habla
     * Uruguay. Un falso positivo cuesta poco: el equipo devuelve la
     * conversación a la IA con un clic.
     *
     * @var list<string>
     */
    private const HOSTILES = [
        '/\b(and[aá]|vay[aá]n?se)\s+a\s+la\s+(mierda|concha|puta)/iu',
        '/\b(la\s+concha\s+de\s+(tu|su)\s+madre|hij[oa]s?\s+de\s+(puta|mil\s+putas))\b/iu',
        '/\b(pelotud[oa]s?|bolud[oa]s?\s+de\s+mierda|imb[eé]cil(es)?|est[uú]pid[oa]s?|idiotas?|tarad[oa]s?|forr[oa]s?|nabos?)\b/iu',
        '/\b(estafador(es)?|estafas?|chorr[oa]s?|ladr[oó]n(es)?|fraudes?|rat[ae]s?)\b/iu',
        '/\b(basura|porquer[ií]a|mierdas?|cagada)\b/iu',
        '/\b(puro\s+verso|pura\s+mentira|son\s+unos\s+chantas|chantas?)\b/iu',
    ];

    /**
     * Pedidos explícitos de una persona: van a la primera, sin preguntar.
     *
     * @var list<string>
     */
    private const PIDE_HUMANO = [
        '/\b(hablar|habl[aá]rlo|comunicarme|atenderme)\s+(con\s+)?(una?\s+)?(persona|humano|asesor|vendedor|encargado|alguien\s+(real|del\s+local))\b/iu',
        '/\b(quiero|necesito|pasame|p[aá]same\s+con|me\s+pas[aá]s\s+con)\s+(una?\s+)?(persona|humano|asesor|vendedor|operador)\b/iu',
        '/\b(atenci[oó]n\s+humana|no\s+quiero\s+(hablar\s+con\s+)?(un\s+)?(bot|robot|m[aá]quina|ia))\b/iu',
    ];

    /**
     * Promesas del modelo de pasar con una persona. Si las escribe sin
     * llamar a `derivar_a_humano`, la persona queda esperando a alguien que
     * nunca llega: la red de seguridad deriva igual.
     *
     * @var list<string>
     */
    private const PROMETE_HUMANO = [
        '/\bte\s+(paso|derivo|comunico|transfiero|contacto|pongo\s+en\s+contacto)\s+con\s+(alguien|una\s+persona|un\s+humano|ventas\b|(el|la|un|una|nuestr[oa]s?|los|las)\s+(asesor|vendedor|encargad|compañer|operador|equipo|persona|taller))/iu',
        '/\bte\s+derivo\b/iu',
        '/\b(una?|el|la|nuestr[oa])\s+(asesor[a]?|vendedor[a]?|encargad[oa]|persona|compañer[oa]|miembro|integrante)\s+(del\s+(equipo|local|taller)\s+)?('.self::VERBO_DE_CONTACTO.')/iu',
        '/\balguien\s+(del\s+(equipo|local|taller)\s+)?('.self::VERBO_DE_CONTACTO.')/iu',
        '/\b(el|nuestro)\s+equipo\s+('.self::VERBO_DE_CONTACTO.')/iu',
        // El verbo antes del sujeto: «en un rato te escribe alguien del equipo».
        '/\b('.self::VERBO_DE_CONTACTO.')\s+(alguien|una\s+persona|(el|nuestro)\s+equipo|una?\s+(asesor[a]?|vendedor[a]?|encargad[oa]|compañer[oa]|miembro|integrante))\b/iu',
        // «Te voy a pasar la información a un miembro del equipo», «le paso tus datos al equipo».
        '/\b(te|le)\s+(paso|pasamos|voy\s+a\s+pasar|vamos\s+a\s+pasar)\b[^.!?\n]{0,40}?\b(a\s+(alguien|una\s+persona|una?\s+(asesor[a]?|vendedor[a]?|encargad[oa]|compañer[oa]|miembro|integrante)|(el|nuestro)\s+equipo)|al\s+equipo)\b/iu',
    ];

    /**
     * Respuestas que dan un turno por reservado. Pasó en vivo con un modelo
     * flash: escribió «Reservado. Tenés un turno…» sin llamar a
     * `reservar_turno`. «¿Te reservo…?» (pregunta, presente) no cuenta.
     *
     * @var list<string>
     */
    private const AFIRMA_RESERVA = [
        '/^\W*(listo\W+)?(reservado|agendado)\b/iu',
        '/\b(qued[oó]|queda|est[aá])\s+(reservado|agendado|anotado)\b/iu',
        // Con tilde: «te reserve» (sin tilde) es el subjuntivo de una pregunta.
        '/\bte\s+(lo\s+)?(reservé|agendé|anoté)/iu',
        '/\bturno\s+(reservado|agendado)\b/iu',
    ];

    /**
     * Lo que sigue al sujeto para que sea una promesa de contacto: «te va a
     * escribir», «se va a comunicar», «te llama». «Te va a esperar en el
     * taller» o «te va a mostrar el auto» no prometen que alguien escriba.
     */
    private const VERBO_DE_CONTACTO = 'te\s+van?\s+a\s+(escribir|contactar|comunicar|llamar|atender|responder)'
        .'|se\s+van?\s+a\s+(comunicar|contactar|poner)'
        .'|te\s+(escribe|contacta|llama|atiende|responde)n?\b';

    public static function esHostil(string $texto): bool
    {
        foreach (self::HOSTILES as $patron) {
            if (preg_match($patron, $texto) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Cuántos mensajes hostiles seguidos hay al final. Uno tranquilo corta.
     *
     * @param  list<string>  $textosDelCliente  del más viejo al más nuevo
     */
    public static function rachaHostil(array $textosDelCliente): int
    {
        $racha = 0;

        foreach (array_reverse($textosDelCliente) as $texto) {
            if (! self::esHostil($texto)) {
                break;
            }

            $racha++;
        }

        return $racha;
    }

    public static function pideHumano(string $texto): bool
    {
        foreach (self::PIDE_HUMANO as $patron) {
            if (preg_match($patron, $texto) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Si el texto que sale le promete a la persona que la va a atender
     * alguien del equipo («te paso con», «un asesor te va a escribir»).
     */
    public static function prometeHumano(string $texto): bool
    {
        foreach (self::PROMETE_HUMANO as $patron) {
            if (preg_match($patron, $texto) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Si la respuesta le dice a la persona que su turno quedó reservado. El
     * job la deja salir sólo si el turno existe de verdad.
     */
    public static function afirmaReserva(string $texto): bool
    {
        foreach (self::AFIRMA_RESERVA as $patron) {
            if (preg_match($patron, $texto) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Por qué una respuesta no puede salir, o null si puede.
     *
     * Dos fallos que el cliente sí ve y que ningún prompt evita del todo:
     * repetirle palabra por palabra un mensaje que ya tiene, y mandarle una
     * nota interna entre paréntesis («(registro actualizado)»).
     *
     * @param  list<string>  $previos  lo que ya se le mandó
     */
    public static function respuestaInvalida(string $texto, array $previos): ?string
    {
        $plano = trim((string) preg_replace('/\s+/u', ' ', $texto));

        if ($plano === '') {
            return 'vacía';
        }

        if (preg_match('/^[(\[].*[)\]]$/su', $plano) === 1) {
            return 'era una nota interna entre paréntesis';
        }

        if (mb_strlen($plano) >= 40) {
            foreach ($previos as $previo) {
                if ($plano === trim((string) preg_replace('/\s+/u', ' ', $previo))) {
                    return 'repetía palabra por palabra un mensaje anterior';
                }
            }
        }

        return null;
    }
}
