FROM squidfunk/mkdocs-material:latest

USER root

RUN pip install mkdocs-static-i18n

WORKDIR /docs

EXPOSE 8000