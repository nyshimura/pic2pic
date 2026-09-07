from fastapi import FastAPI, UploadFile, File
from pydantic import BaseModel
import face_recognition
from io import BytesIO
import uvicorn
from PIL import Image, ImageOps
import numpy as np
import requests
import base64

app = FastAPI(title="API de Reconhecimento Facial - Eventos")

def preparar_imagem(imagem_bytes):
    img = Image.open(BytesIO(imagem_bytes))
    img = ImageOps.exif_transpose(img)
    img = img.convert('RGB')
    
    img.thumbnail((1800, 1800))
    
    array_img = np.array(img, dtype=np.uint8)
    array_img_alinhada = np.ascontiguousarray(array_img)
    return array_img_alinhada

@app.post("/comparar_rostos/")
async def comparar_rostos(selfie: UploadFile = File(...), foto_evento: UploadFile = File(...)):
    try:
        selfie_bytes = await selfie.read()
        evento_bytes = await foto_evento.read()
        selfie_img = preparar_imagem(selfie_bytes)
        evento_img = preparar_imagem(evento_bytes)
        selfie_encodings = face_recognition.face_encodings(selfie_img)
        evento_encodings = face_recognition.face_encodings(evento_img)

        if len(selfie_encodings) == 0:
            return {"sucesso": False, "erro": "Nenhum rosto detectado na selfie."}
        if len(evento_encodings) == 0:
            return {"sucesso": False, "erro": "Nenhum rosto detectado na foto do evento."}

        selfie_encoding = selfie_encodings[0]
        match = False
        for rosto_evento in evento_encodings:
            resultado = face_recognition.compare_faces([selfie_encoding], rosto_evento, tolerance=0.6)
            if resultado[0]:
                match = True
                break
        return {"sucesso": True, "match": match}
    except Exception as e:
        return {"sucesso": False, "erro": str(e)}


class RequestURL(BaseModel):
    image_url: str

class RequestBase64(BaseModel):
    selfie_base64: str

def extrair_rostos_avancado(imagem_img):
    # 1. Varredura Normal (Em pé)
    locais = face_recognition.face_locations(imagem_img, model="hog")
    if len(locais) > 0:
        encodings = face_recognition.face_encodings(imagem_img, known_face_locations=locais)
        return [enc.tolist() for enc in encodings]
        
    # 2. Plano B (Zoom 2x para longe)
    locais = face_recognition.face_locations(imagem_img, number_of_times_to_upsample=2, model="hog")
    if len(locais) > 0:
        encodings = face_recognition.face_encodings(imagem_img, known_face_locations=locais)
        return [enc.tolist() for enc in encodings]

    # 3. Plano C (O Giro de Pescoço - Roda 90 graus)
    img_90 = np.rot90(imagem_img)
    locais = face_recognition.face_locations(img_90, model="hog")
    if len(locais) > 0:
        encodings = face_recognition.face_encodings(img_90, known_face_locations=locais)
        return [enc.tolist() for enc in encodings]
        
    # 4. Plano D (O Giro de Pescoço - Roda -90 graus)
    img_270 = np.rot90(imagem_img, 3)
    locais = face_recognition.face_locations(img_270, model="hog")
    if len(locais) > 0:
        encodings = face_recognition.face_encodings(img_270, known_face_locations=locais)
        return [enc.tolist() for enc in encodings]

    # Se nada funcionar (como a foto cortada), aceita o destino e devolve vazio
    return []

@app.post("/extrair_url/")
async def extrair_url(req: RequestURL):
    try:
        resposta = requests.get(req.image_url, timeout=10)
        imagem_img = preparar_imagem(resposta.content)
        lista_rostos = extrair_rostos_avancado(imagem_img)
        
        if len(lista_rostos) == 0:
            return {"sucesso": False, "erro": "Nenhum rosto encontrado"}
            
        return {"sucesso": True, "encodings": lista_rostos}
    except Exception as e:
        return {"sucesso": False, "erro": str(e)}

@app.post("/extrair_base64/")
async def extrair_base64(req: RequestBase64):
    try:
        b64_string = req.selfie_base64
        if "," in b64_string:
            b64_string = b64_string.split(",")[1]
            
        imagem_bytes = base64.b64decode(b64_string)
        imagem_img = preparar_imagem(imagem_bytes)
        
        lista_rostos = extrair_rostos_avancado(imagem_img)
        
        if len(lista_rostos) == 0:
            return {"sucesso": False, "erro": "Rosto não detectado"}
            
        return {"sucesso": True, "encodings": lista_rostos}
    except Exception as e:
        return {"sucesso": False, "erro": str(e)}

if __name__ == "__main__":
    uvicorn.run(app, host="0.0.0.0", port=7860)