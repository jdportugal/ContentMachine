#!/usr/bin/env python3
"""
Transcrição local com Whisper (word-level) — o equivalente independente ao
endpoint /generate-subtitles do serviço Flask "ShortsCreator".

Corre com faster-whisper (preferido, sem torch) ou, em alternativa, com o
openai-whisper. Imprime em stdout o `subtitle_data` no MESMO formato do
original, para ser deslocado por clip e gravado como legendas:

    [{"start": float, "end": float, "text": str,
      "words": [{"word": str, "start": float, "end": float}]}]

Uso:
    python3 transcribe.py --input video.mp4 --language pt --model tiny
"""
import argparse
import json
import sys


def transcribe_faster_whisper(path, language, model_name):
    from faster_whisper import WhisperModel

    model = WhisperModel(model_name, device="cpu", compute_type="int8")
    segments, _info = model.transcribe(
        path, language=language, word_timestamps=True
    )

    out = []
    for seg in segments:
        start = max(0.0, float(seg.start))
        end = max(start + 0.1, float(seg.end))
        words = []
        for w in (seg.words or []):
            ws = max(0.0, float(w.start))
            we = max(ws + 0.05, float(w.end))
            words.append({"word": w.word, "start": ws, "end": we})
        out.append({
            "start": start,
            "end": end,
            "text": seg.text.strip(),
            "words": words,
        })
    return out


def transcribe_openai_whisper(path, language, model_name):
    import whisper

    model = whisper.load_model(model_name)
    result = model.transcribe(path, language=language, word_timestamps=True)

    out = []
    for seg in result.get("segments", []):
        start = max(0.0, float(seg["start"]))
        end = max(start + 0.1, float(seg["end"]))
        words = []
        for w in seg.get("words", []) or []:
            ws = max(0.0, float(w["start"]))
            we = max(ws + 0.05, float(w["end"]))
            words.append({"word": w["word"], "start": ws, "end": we})
        out.append({
            "start": start,
            "end": end,
            "text": seg["text"].strip(),
            "words": words,
        })
    return out


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--input", required=True)
    parser.add_argument("--language", default="pt")
    parser.add_argument("--model", default="tiny")
    args = parser.parse_args()

    try:
        try:
            data = transcribe_faster_whisper(args.input, args.language, args.model)
        except ImportError:
            data = transcribe_openai_whisper(args.input, args.language, args.model)
    except ImportError:
        sys.stderr.write(
            "Nem faster-whisper nem openai-whisper estão instalados. "
            "Instale um deles: pip install faster-whisper\n"
        )
        sys.exit(2)
    except Exception as exc:  # noqa: BLE001
        sys.stderr.write(f"Erro na transcrição: {exc}\n")
        sys.exit(1)

    json.dump({"subtitle_data": data}, sys.stdout, ensure_ascii=False)
    sys.stdout.flush()


if __name__ == "__main__":
    main()
