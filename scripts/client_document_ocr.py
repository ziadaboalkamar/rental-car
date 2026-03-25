#!/usr/bin/env python3
import argparse
import json
import os
import re
import sys
import tempfile
from concurrent.futures import ThreadPoolExecutor, as_completed
from typing import Any, Dict, List, Optional, Tuple

if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
if hasattr(sys.stderr, "reconfigure"):
    sys.stderr.reconfigure(encoding="utf-8", errors="replace")
try:
    from PIL import Image, ImageOps  # type: ignore
except Exception:
    Image = None
    ImageOps = None

_EASYOCR_READER = None
_PADDLEOCR_READER = None


EngineResult = Tuple[str, float, str, Optional[Dict[str, Optional[str]]]]


def _get_easyocr_reader():
    global _EASYOCR_READER
    if _EASYOCR_READER is None:
        import easyocr  # type: ignore
        try:
            import torch  # type: ignore
            use_gpu = torch.cuda.is_available()
        except Exception:
            use_gpu = False
        _EASYOCR_READER = easyocr.Reader(["en", "ar"], gpu=use_gpu)
    return _EASYOCR_READER


def _get_paddleocr_reader():
    global _PADDLEOCR_READER
    if _PADDLEOCR_READER is None:
        from paddleocr import PaddleOCR  # type: ignore
        _PADDLEOCR_READER = PaddleOCR(use_angle_cls=True, lang="ar")
    return _PADDLEOCR_READER


def _preprocess_single(path: str, max_side: int, grayscale: bool, autocontrast: bool) -> Tuple[str, Optional[str]]:
    if Image is None or ImageOps is None:
        return path, None

    ext = os.path.splitext(path)[1].lower()
    if ext not in {".jpg", ".jpeg", ".png", ".webp", ".bmp"}:
        return path, None

    needs_resize = False
    with Image.open(path) as probe:
        if max_side > 0 and max(probe.size) > max_side:
            needs_resize = True

    if not needs_resize and not grayscale and not autocontrast:
        return path, None

    with Image.open(path) as image:
        image = image.convert("RGB")

        if needs_resize:
            ratio = max_side / float(max(image.size))
            new_size = (
                max(1, int(image.size[0] * ratio)),
                max(1, int(image.size[1] * ratio)),
            )
            image = image.resize(new_size, Image.LANCZOS)

        if grayscale:
            image = ImageOps.grayscale(image)

        if autocontrast:
            image = ImageOps.autocontrast(image)

        tmp = tempfile.NamedTemporaryFile(delete=False, suffix=".jpg")
        tmp.close()
        image.save(tmp.name, format="JPEG", quality=92)
        return tmp.name, tmp.name


def preprocess_paths(paths: List[str], max_side: int, grayscale: bool, autocontrast: bool) -> Tuple[List[str], List[str]]:
    processed: List[str] = []
    temps: List[str] = []

    with ThreadPoolExecutor() as executor:
        futures = {executor.submit(_preprocess_single, path, max_side, grayscale, autocontrast): path for path in paths}
        results = {futures[future]: future.result() for future in as_completed(futures)}

    for original in paths:
        processed_path, temp_path = results[original]
        processed.append(processed_path)
        if temp_path:
            temps.append(temp_path)

    return processed, temps


def _easyocr_single(path: str) -> Tuple[List[str], List[float]]:
    reader = _get_easyocr_reader()
    results = reader.readtext(path, detail=1, paragraph=False)
    lines: List[str] = []
    confidences: List[float] = []

    for item in results:
        if len(item) < 3:
            continue
        text = str(item[1]).strip()
        if text:
            lines.append(text)
        try:
            confidences.append(float(item[2]))
        except Exception:
            pass

    return lines, confidences


def read_with_easyocr(paths: List[str]) -> Optional[EngineResult]:
    try:
        _get_easyocr_reader()
    except Exception:
        return None

    lines: List[str] = []
    confidences: List[float] = []

    with ThreadPoolExecutor() as executor:
        futures = [executor.submit(_easyocr_single, path) for path in paths]
        for future in futures:
            future_lines, future_confidences = future.result()
            lines.extend(future_lines)
            confidences.extend(future_confidences)

    if not lines:
        return None

    confidence = sum(confidences) / len(confidences) if confidences else 0.0
    return ("\n".join(lines), confidence, "easyocr", None)


def _paddle_single(path: str) -> Tuple[List[str], List[float]]:
    ocr = _get_paddleocr_reader()
    results = ocr.ocr(path, cls=True)
    lines: List[str] = []
    confidences: List[float] = []

    for block in results or []:
        for row in block or []:
            if not row or len(row) < 2:
                continue
            text = str(row[1][0]).strip()
            if text:
                lines.append(text)
            try:
                confidences.append(float(row[1][1]))
            except Exception:
                pass

    return lines, confidences


def read_with_paddleocr(paths: List[str]) -> Optional[EngineResult]:
    try:
        _get_paddleocr_reader()
    except Exception:
        return None

    lines: List[str] = []
    confidences: List[float] = []

    with ThreadPoolExecutor() as executor:
        futures = [executor.submit(_paddle_single, path) for path in paths]
        for future in futures:
            future_lines, future_confidences = future.result()
            lines.extend(future_lines)
            confidences.extend(future_confidences)

    if not lines:
        return None

    confidence = sum(confidences) / len(confidences) if confidences else 0.0
    return ("\n".join(lines), confidence, "paddleocr", None)


def read_with_tesseract(paths: List[str]) -> Optional[EngineResult]:
    try:
        import pytesseract  # type: ignore
        from PIL import Image as PILImage  # type: ignore
    except Exception:
        return None

    def _tesseract_single(path: str) -> str:
        return pytesseract.image_to_string(PILImage.open(path), lang="ara+eng").strip()

    lines: List[str] = []
    with ThreadPoolExecutor() as executor:
        futures = [executor.submit(_tesseract_single, path) for path in paths]
        for future in futures:
            text = future.result()
            if text:
                lines.append(text)

    if not lines:
        return None

    return ("\n".join(lines), 0.0, "tesseract", None)


def clean_model_json(raw: str) -> str:
    raw = raw.strip()
    if raw.startswith("```"):
        parts = raw.split("```")
        if len(parts) >= 2:
            raw = parts[1]
        if raw.startswith("json"):
            raw = raw[4:]
    return raw.strip()


def to_document_list(data: Any) -> List[Dict[str, Any]]:
    if isinstance(data, list):
        return [item for item in data if isinstance(item, dict)]
    if isinstance(data, dict):
        return [data]
    return []


def map_ollama_fields(document_type: str, data: Any) -> Dict[str, Optional[str]]:
    documents = to_document_list(data)
    if not documents:
        return {}

    candidate = documents[0]
    for item in documents:
        kind = str(item.get("document_type", "")).strip().lower()
        if document_type == "driver_license" and "licence" in kind:
            candidate = item
            break
        if document_type == "id_card" and "identity" in kind:
            candidate = item
            break
        if document_type == "residency_card" and ("resident" in kind or "residency" in kind):
            candidate = item
            break

    def pick(*keys: str) -> Optional[str]:
        for key in keys:
            value = candidate.get(key)
            if value is None:
                continue
            text = str(value).strip()
            if text != "":
                return text
        return None

    fields: Dict[str, Optional[str]] = {
        "full_name": pick("full_name"),
        "nationality": pick("nationality"),
        "date_of_birth": pick("date_of_birth"),
        "expiry_date": pick("expiry_date"),
        "issue_date": pick("first_issued"),
        "place_of_issue": pick("issue_at"),
    }

    if document_type == "driver_license":
        fields["document_number"] = pick("licence_number", "license_number")
    else:
        fields["document_number"] = pick("civil_number", "document_number")

    return {key: value for key, value in fields.items() if value}


def read_with_ollama(paths: List[str], document_type: str, ollama_model: str) -> Optional[EngineResult]:
    try:
        import ollama  # type: ignore
    except Exception:
        return None

    prompt = f"""Analyze this document image. The expected document type for this request is: {document_type}.

It could still be a driving licence or an ID/resident card, so inspect the image carefully before deciding.

Extract ALL visible text and return it as JSON.

If it is a DRIVING LICENCE, use these fields:
- document_type: \"driving_licence\"
- full_name
- licence_number
- issue_at
- first_issued
- expiry_date
- nationality

If it is an IDENTITY CARD or RESIDENT CARD, use these fields:
- document_type: \"identity_card\" or \"resident_card\"
- full_name
- civil_number
- expiry_date
- date_of_birth
- place_of_birth
- nationality
- profession

If the image contains MULTIPLE cards, return a JSON array with one object per card.

Return ONLY the JSON, no explanation, no markdown."""

    raw_entries: List[str] = []
    merged_fields: Dict[str, Optional[str]] = {}

    for path in paths:
        response = ollama.chat(
            model=ollama_model,
            messages=[{
                "role": "user",
                "content": prompt,
                "images": [path],
            }],
        )

        raw = clean_model_json(str(response["message"]["content"]))
        try:
            parsed = json.loads(raw)
            raw_entries.append(json.dumps(parsed, ensure_ascii=True))
            for key, value in map_ollama_fields(document_type, parsed).items():
                if key not in merged_fields or not merged_fields[key]:
                    merged_fields[key] = value
        except Exception:
            raw_entries.append(raw)

    if not raw_entries:
        return None

    return ("\n".join(raw_entries), 0.0, "ollama", merged_fields or None)


def normalize_text(text: str) -> str:
    text = re.sub(r"[ \t]+", " ", text)
    text = re.sub(r"\n{3,}", "\n\n", text)
    return text.strip()


def extract_labeled_value(text: str, patterns: List[str]) -> Optional[str]:
    for pattern in patterns:
        match = re.search(pattern, text, re.IGNORECASE | re.MULTILINE)
        if match:
            value = match.group(1).strip(" :.-")
            if value:
                return value
    return None


def extract_dates(text: str) -> List[str]:
    matches = re.findall(r"\b(?:\d{4}[-/]\d{2}[-/]\d{2}|\d{2}[-/]\d{2}[-/]\d{4})\b", text)
    return list(dict.fromkeys(matches))


def extract_document_number(text: str) -> Optional[str]:
    labeled = extract_labeled_value(text, [
        r"(?:license\s*(?:no|number)|id\s*(?:no|number)|passport\s*(?:no|number)|document\s*(?:no|number))\s*[:#-]?\s*([A-Z0-9\-]{5,30})",
        r"(?:ط±ظ‚ظ…\s*(?:ط§ظ„ظ‡ظˆظٹط©|ط§ظ„ط¬ظˆط§ط²|ط§ظ„ط±ط®طµط©))\s*[:#-]?\s*([A-Z0-9\-]{5,30})",
    ])
    if labeled:
        return labeled

    candidates = re.findall(r"\b[A-Z0-9\-]{6,20}\b", text)
    return max(candidates, key=len) if candidates else None


def extract_name(text: str) -> Optional[str]:
    labeled = extract_labeled_value(text, [
        r"(?:name|full name|surname)\s*[:#-]?\s*([A-Z][A-Z\s]{4,}|[A-Za-z][A-Za-z\s]{4,})",
        r"(?:ط§ظ„ط§ط³ظ…|ط§ط³ظ… ط­ط§ظ…ظ„ ط§ظ„ظ‡ظˆظٹط©|ط§ط³ظ… طµط§ط­ط¨ ط§ظ„ط±ط®طµط©)\s*[:#-]?\s*([^\n]{4,})",
    ])
    if labeled:
        return labeled

    for line in text.splitlines():
        clean = line.strip()
        if len(clean) < 5 or re.search(r"\d", clean):
            continue
        if len(clean.split()) >= 2:
            return clean
    return None


def extract_fields(document_type: str, text: str) -> Dict[str, Optional[str]]:
    dates = extract_dates(text)
    fields: Dict[str, Optional[str]] = {
        "document_number": extract_document_number(text),
        "full_name": extract_name(text),
        "date_of_birth": extract_labeled_value(text, [
            r"(?:date of birth|birth date|dob)\s*[:#-]?\s*([0-9/\-]{8,10})",
            r"(?:طھط§ط±ظٹط® ط§ظ„ظ…ظٹظ„ط§ط¯)\s*[:#-]?\s*([0-9/\-]{8,10})",
        ]),
        "expiry_date": extract_labeled_value(text, [
            r"(?:expiry date|expiration date|expires)\s*[:#-]?\s*([0-9/\-]{8,10})",
            r"(?:طھط§ط±ظٹط® ط§ظ„ط§ظ†طھظ‡ط§ط،|طھط§ط±ظٹط® ط§ظ†طھظ‡ط§ط،)\s*[:#-]?\s*([0-9/\-]{8,10})",
        ]),
        "issue_date": extract_labeled_value(text, [
            r"(?:issue date|issued)\s*[:#-]?\s*([0-9/\-]{8,10})",
            r"(?:طھط§ط±ظٹط® ط§ظ„ط§طµط¯ط§ط±|طھط§ط±ظٹط® ط§ظ„ط¥طµط¯ط§ط±)\s*[:#-]?\s*([0-9/\-]{8,10})",
        ]),
        "nationality": extract_labeled_value(text, [
            r"(?:nationality)\s*[:#-]?\s*([^\n]{3,})",
            r"(?:ط§ظ„ط¬ظ†ط³ظٹط©)\s*[:#-]?\s*([^\n]{3,})",
        ]),
        "license_class": extract_labeled_value(text, [
            r"(?:class|license class)\s*[:#-]?\s*([A-Z0-9]{1,10})",
            r"(?:ظپط¦ط© ط§ظ„ط±ط®طµط©)\s*[:#-]?\s*([^\n]{1,10})",
        ]),
        "address": extract_labeled_value(text, [
            r"(?:address)\s*[:#-]?\s*([^\n]{6,})",
            r"(?:ط§ظ„ط¹ظ†ظˆط§ظ†)\s*[:#-]?\s*([^\n]{6,})",
        ]),
        "place_of_issue": extract_labeled_value(text, [
            r"(?:place of issue|issued at)\s*[:#-]?\s*([^\n]{3,})",
            r"(?:ظ…ظƒط§ظ† ط§ظ„ط§طµط¯ط§ط±|ظ…ظƒط§ظ† ط§ظ„ط¥طµط¯ط§ط±)\s*[:#-]?\s*([^\n]{3,})",
        ]),
    }

    if not fields["date_of_birth"] and dates:
        fields["date_of_birth"] = dates[0]
    if not fields["expiry_date"] and len(dates) >= 2:
        fields["expiry_date"] = dates[-1]
    if document_type.startswith("driver_license") and not fields["license_class"]:
        match = re.search(r"\b(?:class|cls)\s*[:#-]?\s*([A-Z0-9]{1,4})\b", text, re.IGNORECASE)
        if match:
            fields["license_class"] = match.group(1)

    return {key: value for key, value in fields.items() if value}


def run(
    paths: List[str],
    document_type: str,
    engine: str,
    tesseract_cmd: str,
    ollama_model: str,
    max_side: int,
    grayscale: bool,
    autocontrast: bool,
) -> Dict[str, object]:
    available_paths = [path for path in paths if os.path.exists(path)]
    if not available_paths:
        raise RuntimeError("No readable files were passed to OCR.")

    prepared_paths, temp_paths = preprocess_paths(available_paths, max_side, grayscale, autocontrast)

    try:
        if tesseract_cmd:
            try:
                import pytesseract  # type: ignore
                pytesseract.pytesseract.tesseract_cmd = tesseract_cmd
            except Exception:
                pass

        if engine == "ollama":
            result = read_with_ollama(prepared_paths, document_type, ollama_model)
        elif engine == "easyocr":
            result = read_with_easyocr(prepared_paths)
        elif engine == "paddleocr":
            result = read_with_paddleocr(prepared_paths)
        elif engine == "tesseract":
            result = read_with_tesseract(prepared_paths)
        else:
            result = (
                read_with_ollama(prepared_paths, document_type, ollama_model)
                or read_with_tesseract(prepared_paths)
                or read_with_paddleocr(prepared_paths)
                or read_with_easyocr(prepared_paths)
            )
    finally:
        for temp_path in temp_paths:
            try:
                os.unlink(temp_path)
            except OSError:
                pass

    if result is None:
        raise RuntimeError("No local OCR engine is installed. Install ollama, EasyOCR, PaddleOCR, or pytesseract.")

    raw_text, confidence, used_engine, fields_override = result
    raw_text = normalize_text(raw_text)

    return {
        "provider": "local_python",
        "engine": used_engine,
        "document_type": document_type,
        "confidence": round(float(confidence), 4),
        "raw_text": raw_text,
        "raw_output": {
            "raw_text": raw_text,
            "engine": used_engine,
        },
        "fields": fields_override or extract_fields(document_type, raw_text),
    }


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--document-type", required=True)
    parser.add_argument("--engine", default="auto", choices=["auto", "ollama", "easyocr", "paddleocr", "tesseract"])
    parser.add_argument("--tesseract-cmd", default="")
    parser.add_argument("--ollama-model", default="llava")
    parser.add_argument("--max-side", type=int, default=1600)
    parser.add_argument("--grayscale", action="store_true")
    parser.add_argument("--autocontrast", action="store_true")
    parser.add_argument("paths", nargs="+")
    args = parser.parse_args()

    try:
        payload = run(
            args.paths,
            args.document_type,
            args.engine,
            args.tesseract_cmd,
            args.ollama_model,
            args.max_side,
            args.grayscale,
            args.autocontrast,
        )
        sys.stdout.write(json.dumps(payload, ensure_ascii=True))
        return 0
    except Exception as exc:
        sys.stderr.write(str(exc))
        return 1


if __name__ == "__main__":
    raise SystemExit(main())

