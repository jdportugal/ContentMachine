import React from "react";
import { AbsoluteFill, Audio, Img, Sequence, interpolate, spring, staticFile, useCurrentFrame, useVideoConfig } from "remotion";
import { loadThemeFonts } from "../fonts";

/**
 * A blog short for Imocena's socials, in the ads' design language (AdImocena): navy,
 * Cormorant Garamond headlines with a coral word, Poppins text, a house or a person
 * behind the words, big captions of the voiceover, and the imocena.com call to action.
 * Each video is a list of scenes; the first one is the opening, and its kind changes
 * from video to video, so the feed never sees the same start twice.
 */

export type TipoCena =
  | "foto" // a photo behind an eyebrow + headline
  | "pergunta" // a big question, no photo
  | "numero" // one number, huge, counting up
  | "antes-depois" // two photos side by side, left dim, right bright
  | "erro" // a wrong idea in quotes, struck through, then the right one
  | "lista" // up to 4 items ticked in, over a dimmed photo
  | "telemovel" // a phone showing a photo, text beside it
  | "cta"; // the close: imocena.com

export type Cena = {
  tipo: TipoCena;
  segundos: number;
  eyebrow?: string;
  titulo?: string; // *word* in coral
  texto?: string;
  itens?: string[];
  numero?: string; // "120 m", "1h23", "51%"
  foto?: string;
  fotoB?: string;
  legenda?: string; // the words spoken in this scene, shown as captions
  audio?: string; // this scene's voiceover
};

export type BlogShortProps = { cenas: Cena[]; voz?: string | null; musica?: string | null };

const FPS = 30;
export const duracaoBlog = (p: BlogShortProps) => Math.max(30, Math.round(p.cenas.reduce((s, c) => s + c.segundos, 0) * FPS));

const C = { navy: "#0E1626", navy2: "#141F33", cyan: "#05B8E6", coral: "#F06052", ivory: "#F4F1EA", soft: "#AEB5C1" };
const DISPLAY = "'Cormorant Garamond', Georgia, serif";
const SANS = "'Poppins', 'Helvetica Neue', Arial, sans-serif";
const src = (f: string) => (/^https?:/.test(f) ? f : staticFile(f));

const useEntrada = (de = 0, amortecimento = 14) => {
  const f = useCurrentFrame();
  return spring({ frame: f - de, fps: FPS, config: { damping: amortecimento, mass: 0.7 } });
};

/** "a diferença é a *hora*" → the starred words in coral. */
const Destaque: React.FC<{ texto: string }> = ({ texto }) => (
  <>
    {texto.split(/(\*[^*]+\*)/).map((p, i) =>
      p.startsWith("*") ? <span key={i} style={{ color: C.coral }}>{p.slice(1, -1)}</span> : <React.Fragment key={i}>{p}</React.Fragment>,
    )}
  </>
);

const Marca: React.FC = () => (
  <div style={{ position: "absolute", top: 90, left: 0, right: 0, display: "flex", justifyContent: "center", alignItems: "baseline", gap: 14 }}>
    <span style={{ fontFamily: DISPLAY, fontSize: 58, fontWeight: 600, color: C.ivory, letterSpacing: 1 }}>Imocena</span>
    <span style={{ width: 13, height: 13, borderRadius: 7, background: C.cyan, display: "inline-block" }} />
  </div>
);

const Fundo: React.FC = () => (
  <AbsoluteFill style={{ background: `radial-gradient(1100px 800px at 85% 0%, rgba(5,184,230,.16), transparent 60%), radial-gradient(900px 700px at 0% 100%, rgba(240,96,82,.12), transparent 60%), ${C.navy}` }} />
);

const Foto: React.FC<{ foto?: string; escurecer?: number; deslocar?: "in" | "out" | "esq"; topo?: boolean }> = ({ foto, escurecer = 0.62, deslocar = "in", topo }) => {
  const f = useCurrentFrame();
  if (!foto) return <Fundo />;
  const s = deslocar === "out" ? interpolate(f, [0, 150], [1.2, 1.08], { extrapolateRight: "clamp" }) : interpolate(f, [0, 150], [1.06, 1.18], { extrapolateRight: "clamp" });
  const x = deslocar === "esq" ? interpolate(f, [0, 150], [30, -30], { extrapolateRight: "clamp" }) : 0;
  return (
    <AbsoluteFill>
      <Img src={src(foto)} style={{ width: "100%", height: "100%", objectFit: "cover", transform: `scale(${s}) translateX(${x}px)` }} />
      <AbsoluteFill style={{ background: topo ? `linear-gradient(180deg, rgba(14,22,38,.45) 0%, rgba(14,22,38,.05) 10%, rgba(14,22,38,.08) 34%, rgba(14,22,38,.86) 47%, ${C.navy} 60%)` : `linear-gradient(180deg, rgba(14,22,38,${escurecer - 0.15}) 0%, rgba(14,22,38,${escurecer}) 45%, rgba(14,22,38,${Math.min(1, escurecer + 0.25)}) 80%, ${C.navy} 100%)` }} />
    </AbsoluteFill>
  );
};

const Eyebrow: React.FC<{ children: React.ReactNode; cor?: string; centro?: boolean }> = ({ children, cor = C.cyan, centro }) => (
  <div style={{ display: "flex", alignItems: "center", justifyContent: centro ? "center" : "flex-start", gap: 20 }}>
    <span style={{ width: 64, height: 2, background: cor }} />
    <span style={{ fontFamily: SANS, fontSize: 28, letterSpacing: 8, color: cor, fontWeight: 600, textTransform: "uppercase", textShadow: "0 2px 12px rgba(0,0,0,.6)" }}>{children}</span>
  </div>
);

const Titulo: React.FC<{ texto: string; tamanho?: number; de?: number; centro?: boolean }> = ({ texto, tamanho = 120, de = 0, centro }) => {
  const e = useEntrada(de, 13);
  return (
    <div style={{ fontFamily: DISPLAY, fontWeight: 700, fontSize: tamanho, lineHeight: 1.0, color: C.ivory, textAlign: centro ? "center" : "left", opacity: e, textShadow: "0 4px 30px rgba(0,0,0,.55)", transform: `translateY(${(1 - e) * 40}px)`, textWrap: "balance" as never }}>
      <Destaque texto={texto} />
    </div>
  );
};

/** The voiceover, big, the current word in coral (most people watch muted). */
const Legenda: React.FC<{ texto: string; segundos: number }> = ({ texto, segundos }) => {
  const f = useCurrentFrame();
  const palavras = texto.split(/\s+/).filter(Boolean);
  if (palavras.length === 0) return null;
  // Show it in chunks of ~6 words, so the caption never covers the scene.
  const pedaco = 6;
  const total = Math.max(1, segundos * FPS - 8);
  const atual = Math.min(palavras.length - 1, Math.floor((f / total) * palavras.length));
  const inicio = Math.floor(atual / pedaco) * pedaco;
  const visiveis = palavras.slice(inicio, inicio + pedaco);
  return (
    <div style={{ position: "absolute", left: 70, right: 70, bottom: 190, textAlign: "center" }}>
      <p style={{ margin: 0, fontFamily: SANS, fontWeight: 700, fontSize: 54, lineHeight: 1.22, color: C.ivory, textShadow: "0 4px 24px rgba(0,0,0,.6)" }}>
        {visiveis.map((p, i) => (
          <span key={i} style={{ color: inicio + i === atual ? C.coral : C.ivory }}>{p} </span>
        ))}
      </p>
    </div>
  );
};

/** Where the words go: under the photo when there is one, centred otherwise. */
const zona = (foto?: string): React.CSSProperties => ({ position: "absolute", left: 80, right: 80, top: foto ? 900 : 0, bottom: foto ? 330 : 380, display: "flex", flexDirection: "column", justifyContent: "center" });

const Visto: React.FC<{ tamanho: number; cor: string }> = ({ tamanho, cor }) => (
  <svg width={tamanho} height={tamanho} viewBox="0 0 24 24" fill="none" stroke={cor} strokeWidth={3.2} strokeLinecap="round" strokeLinejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5" /></svg>
);

// ── scenes ────────────────────────────────────────────────────────────────

const CenaFoto: React.FC<{ c: Cena }> = ({ c }) => (
  <AbsoluteFill>
    <Foto foto={c.foto} escurecer={0.72} />
    <Marca />
    <div style={{ position: "absolute", left: 80, right: 80, top: 760, display: "flex", flexDirection: "column", gap: 34 }}>
      {c.eyebrow && <Eyebrow>{c.eyebrow}</Eyebrow>}
      {c.titulo && <Titulo texto={c.titulo} tamanho={124} />}
      {c.texto && <div style={{ fontFamily: SANS, fontSize: 42, lineHeight: 1.4, color: "#D5D9E0" }}>{c.texto}</div>}
    </div>
  </AbsoluteFill>
);

const CenaPergunta: React.FC<{ c: Cena }> = ({ c }) => {
  const f = useCurrentFrame();
  const linha = interpolate(f, [10, 26], [0, 1], { extrapolateLeft: "clamp", extrapolateRight: "clamp" });
  return (
    <AbsoluteFill>
      {c.foto ? <Foto foto={c.foto} deslocar="out" topo /> : <Fundo />}
      <Marca />
      <div style={{ ...zona(c.foto), alignItems: "center", left: 60, right: 60 }}>
        {c.eyebrow && <Eyebrow centro>{c.eyebrow}</Eyebrow>}
        <div style={{ marginTop: 40 }}><Titulo texto={c.titulo ?? ""} tamanho={150} centro /></div>
        <div style={{ marginTop: 40, height: 8, width: 360 * linha, borderRadius: 4, background: C.coral }} />
      </div>
    </AbsoluteFill>
  );
};

const CenaNumero: React.FC<{ c: Cena }> = ({ c }) => {
  const f = useCurrentFrame();
  const a = useEntrada(-4, 12);
  const m = /^([^0-9]*)([0-9]+(?:[.,][0-9]+)?)(.*)$/.exec(c.numero ?? "");
  let mostrado = c.numero ?? "";
  if (m) {
    const alvo = parseFloat(m[2].replace(",", "."));
    const casas = (m[2].split(/[.,]/)[1] ?? "").length;
    const v = interpolate(f, [0, 24], [0, alvo], { extrapolateLeft: "clamp", extrapolateRight: "clamp" });
    mostrado = m[1] + v.toFixed(casas).replace(".", ",") + m[3];
  }
  return (
    <AbsoluteFill>
      {c.foto ? <Foto foto={c.foto} topo /> : <Fundo />}
      <Marca />
      <div style={{ ...zona(c.foto), alignItems: "center", textAlign: "center", left: 60, right: 60 }}>
        {c.eyebrow && <Eyebrow centro>{c.eyebrow}</Eyebrow>}
        <div style={{ marginTop: 20, fontFamily: DISPLAY, fontWeight: 700, fontSize: Math.min(330, Math.floor(1750 / Math.max(4, (c.numero ?? "").length))), lineHeight: 0.95, color: C.coral, opacity: a, transform: `scale(${0.7 + 0.3 * a})`, whiteSpace: "nowrap" }}>{mostrado}</div>
        {c.titulo && <div style={{ marginTop: 30 }}><Titulo texto={c.titulo} tamanho={84} de={8} centro /></div>}
      </div>
    </AbsoluteFill>
  );
};

const CenaAntesDepois: React.FC<{ c: Cena }> = ({ c }) => {
  const f = useCurrentFrame();
  const corte = interpolate(f, [6, 30], [100, 50], { extrapolateLeft: "clamp", extrapolateRight: "clamp" });
  const s = interpolate(f, [0, 150], [1.05, 1.14], { extrapolateRight: "clamp" });
  const rotulo = (t: string, lado: "l" | "r") => (
    <div style={{ position: "absolute", top: 300, [lado === "l" ? "left" : "right"]: 50, padding: "12px 28px", borderRadius: 40, background: lado === "l" ? "rgba(14,22,38,.8)" : C.coral, color: lado === "l" ? C.ivory : C.navy, fontFamily: SANS, fontWeight: 700, fontSize: 30, letterSpacing: 4 }}>{t}</div>
  );
  return (
    <AbsoluteFill style={{ background: C.navy }}>
      <AbsoluteFill>
        {c.foto && <Img src={src(c.foto)} style={{ width: "100%", height: "100%", objectFit: "cover", transform: `scale(${s})`, filter: "brightness(.55) saturate(.6)" }} />}
      </AbsoluteFill>
      <AbsoluteFill style={{ clipPath: `inset(0 0 0 ${corte}%)` }}>
        {(c.fotoB ?? c.foto) && <Img src={src((c.fotoB ?? c.foto) as string)} style={{ width: "100%", height: "100%", objectFit: "cover", transform: `scale(${s})` }} />}
      </AbsoluteFill>
      <div style={{ position: "absolute", top: 0, bottom: 0, left: `${corte}%`, width: 6, marginLeft: -3, background: C.ivory, boxShadow: "0 0 30px rgba(0,0,0,.5)" }} />
      <AbsoluteFill style={{ background: "linear-gradient(180deg, rgba(14,22,38,.55) 0%, transparent 22%, rgba(14,22,38,.35) 40%, rgba(14,22,38,.9) 58%, rgba(14,22,38,.96) 100%)" }} />
      <Marca />
      {rotulo("ANTES", "l")}
      {rotulo("DEPOIS", "r")}
      <div style={{ position: "absolute", left: 80, right: 80, top: 1060 }}>
        {c.titulo && <Titulo texto={c.titulo} tamanho={110} de={10} />}
      </div>
    </AbsoluteFill>
  );
};

const CenaErro: React.FC<{ c: Cena }> = ({ c }) => {
  const f = useCurrentFrame();
  const r = interpolate(f, [16, 28], [0, 1], { extrapolateLeft: "clamp", extrapolateRight: "clamp" });
  const e = useEntrada(0);
  return (
    <AbsoluteFill>
      {c.foto ? <Foto foto={c.foto} topo /> : <Fundo />}
      <Marca />
      <div style={{ ...zona(c.foto), gap: 40 }}>
        <Eyebrow cor={C.coral}>{c.eyebrow ?? "erro comum"}</Eyebrow>
        <div style={{ position: "relative", display: "inline-block", fontFamily: DISPLAY, fontWeight: 700, fontSize: 124, lineHeight: 1.05, color: r > 0.6 ? C.soft : C.ivory, opacity: e }}>
          «{c.titulo}»
          <span style={{ position: "absolute", left: -8, top: "52%", height: 10, borderRadius: 5, background: C.coral, width: `calc(${r * 100}% + 16px)` }} />
        </div>
        {c.texto && <div style={{ fontFamily: SANS, fontWeight: 600, fontSize: 60, lineHeight: 1.3, color: C.ivory, opacity: interpolate(f, [26, 36], [0, 1], { extrapolateLeft: "clamp", extrapolateRight: "clamp" }) }}><Destaque texto={c.texto} /></div>}
      </div>
    </AbsoluteFill>
  );
};

const CenaLista: React.FC<{ c: Cena }> = ({ c }) => {
  const f = useCurrentFrame();
  const itens = (c.itens ?? []).slice(0, 4);
  return (
    <AbsoluteFill>
      <Foto foto={c.foto} deslocar="esq" topo />
      <Marca />
      <div style={{ ...zona(c.foto), top: c.foto ? 820 : 0, gap: 26 }}>
        {c.eyebrow && <Eyebrow>{c.eyebrow}</Eyebrow>}
        {c.titulo && <Titulo texto={c.titulo} tamanho={104} />}
        <div style={{ marginTop: 30, display: "flex", flexDirection: "column", gap: 40 }}>
          {itens.map((t, i) => {
            const e = spring({ frame: f - (8 + i * 9), fps: FPS, config: { damping: 14, mass: 0.7 } });
            return (
              <div key={t} style={{ display: "flex", alignItems: "center", gap: 28, opacity: e, transform: `translateX(${(1 - e) * 60}px)` }}>
                <span style={{ width: 72, height: 72, borderRadius: 18, border: `3px solid ${C.cyan}`, display: "flex", alignItems: "center", justifyContent: "center", flexShrink: 0 }}><Visto tamanho={44} cor={C.cyan} /></span>
                <span style={{ fontFamily: SANS, fontWeight: 600, fontSize: 56, lineHeight: 1.2, color: C.ivory }}><Destaque texto={t} /></span>
              </div>
            );
          })}
        </div>
      </div>
    </AbsoluteFill>
  );
};

const CenaTelemovel: React.FC<{ c: Cena }> = ({ c }) => {
  const e = useEntrada(0, 13);
  const largura = 470;
  const altura = Math.round(largura * 0.93 * (16 / 9) + largura * 0.07);
  return (
    <AbsoluteFill>
      <Fundo />
      <Marca />
      <div style={{ position: "absolute", top: 300, left: 70, opacity: e, transform: `translateY(${(1 - e) * 100}px) rotate(${-4 * e}deg)` }}>
        <div style={{ width: largura, height: altura, borderRadius: largura * 0.14, background: "#05070C", padding: largura * 0.035, boxShadow: "0 40px 120px rgba(0,0,0,.6), 0 0 0 3px #2A3446" }}>
          <div style={{ width: "100%", height: "100%", borderRadius: largura * 0.11, overflow: "hidden", background: C.navy2 }}>
            {c.foto && <Img src={src(c.foto)} style={{ width: "100%", height: "100%", objectFit: "cover" }} />}
          </div>
        </div>
      </div>
      <div style={{ position: "absolute", top: 520, left: 590, right: 60, display: "flex", flexDirection: "column", gap: 28 }}>
        {c.eyebrow && <Eyebrow>{c.eyebrow}</Eyebrow>}
        {c.titulo && <Titulo texto={c.titulo} tamanho={84} de={6} />}
      </div>
    </AbsoluteFill>
  );
};

const CenaCTA: React.FC<{ c: Cena }> = ({ c }) => {
  const a = useEntrada(2, 11);
  const b = useEntrada(12);
  const f = useCurrentFrame();
  const pulso = 1 + 0.035 * Math.sin(f / 5);
  return (
    <AbsoluteFill>
      <Foto foto={c.foto} topo />
      <Marca />
      <div style={{ ...zona(c.foto), top: c.foto ? 860 : 0, bottom: c.foto ? 120 : 240, alignItems: "center", textAlign: "center", left: 0, right: 0 }}>
        <div style={{ opacity: a }}><Eyebrow centro>{c.eyebrow ?? "o vídeo do seu imóvel"}</Eyebrow></div>
        <div style={{ marginTop: 30, padding: "0 70px", fontFamily: DISPLAY, fontWeight: 700, fontSize: 132, lineHeight: 1.0, color: C.ivory, opacity: a, transform: `scale(${0.85 + 0.15 * a})` }}>
          <Destaque texto={c.titulo ?? "Pronto em *minutos.*"} />
        </div>
        <div style={{ marginTop: 64, padding: "36px 90px", borderRadius: 90, background: C.coral, fontFamily: SANS, fontWeight: 700, fontSize: 76, color: C.navy, opacity: b, transform: `scale(${(0.8 + 0.2 * b) * pulso})` }}>imocena.com</div>
        <div style={{ marginTop: 36, fontFamily: SANS, fontSize: 44, color: C.ivory, opacity: b }}>{c.texto ?? "Cole o link do anúncio. O primeiro é grátis."}</div>
      </div>
    </AbsoluteFill>
  );
};

const CENAS: Record<TipoCena, React.FC<{ c: Cena }>> = {
  foto: CenaFoto, pergunta: CenaPergunta, numero: CenaNumero, "antes-depois": CenaAntesDepois,
  erro: CenaErro, lista: CenaLista, telemovel: CenaTelemovel, cta: CenaCTA,
};

/** A short fade between scenes, so cuts never jump. */
const Transicao: React.FC<{ dur: number; children: React.ReactNode }> = ({ dur, children }) => {
  const f = useCurrentFrame();
  const o = interpolate(f, [0, 6, dur - 6, dur], [0, 1, 1, 0.85], { extrapolateLeft: "clamp", extrapolateRight: "clamp" });
  return <AbsoluteFill style={{ opacity: o }}>{children}</AbsoluteFill>;
};

export const BlogShort: React.FC<BlogShortProps> = ({ cenas, voz, musica }) => {
  const { durationInFrames } = useVideoConfig();
  loadThemeFonts("Cormorant Garamond", "Poppins");
  let inicio = 0;
  return (
    <AbsoluteFill style={{ background: C.navy }}>
      {voz && <Audio src={src(voz)} />}
      {musica && (
        <Audio src={src(musica)} volume={(f) => 0.12 * interpolate(f, [durationInFrames - 15, durationInFrames], [1, 0], { extrapolateLeft: "clamp", extrapolateRight: "clamp" })} />
      )}
      {cenas.map((c, i) => {
        const de = Math.round(inicio * FPS);
        const dur = Math.max(1, Math.round(c.segundos * FPS));
        inicio += c.segundos;
        const Componente = CENAS[c.tipo] ?? CenaFoto;
        return (
          <Sequence key={i} from={de} durationInFrames={dur} name={`${i + 1}-${c.tipo}`}>
            {c.audio && <Audio src={src(c.audio)} />}
            <Transicao dur={dur}><Componente c={c} /></Transicao>
            {c.tipo !== "cta" && c.legenda && <Legenda texto={c.legenda} segundos={c.segundos} />}
          </Sequence>
        );
      })}
    </AbsoluteFill>
  );
};
