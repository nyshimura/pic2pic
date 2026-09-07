# Usa uma imagem oficial do Python leve
FROM python:3.9-slim

# Define o diretório de trabalho dentro do container
WORKDIR /code

# Instala apenas o libx11-dev que é uma dependência gráfica mínima
RUN apt-get update && apt-get install -y \
    libx11-dev \
    && rm -rf /var/lib/apt/lists/*

# PASSO MÁGICO 1: Instala a API, o dlib pré-compilado e as bibliotecas de apoio manualmente
# Forçamos a instalação do numpy 1.26.4 para manter compatibilidade com o dlib-bin
# Adicionados requests e pydantic para as novas rotas de comunicação com a Hostinger
RUN pip install --no-cache-dir fastapi uvicorn python-multipart Pillow "numpy==1.26.4" dlib-bin==19.24.2 click face-recognition-models requests pydantic

# PASSO MÁGICO 2: Instala o face_recognition forçando-o a ignorar a checagem do nome dlib
RUN pip install --no-cache-dir --no-deps face_recognition

# Copia o código da sua API e demais arquivos para o servidor
COPY . .

# Expõe a porta que o Hugging Face usa
EXPOSE 7860

# Comando para iniciar a API
CMD ["uvicorn", "app:app", "--host", "0.0.0.0", "--port", "7860"]