NODE_IMAGE ?= docker.io/library/node:20

.PHONY: build
build:
	podman run --rm -v .:/app:z -w /app $(NODE_IMAGE) sh -c "npm ci && npm run build"

.PHONY: archive
archive:
	rm -f transfer.tar.gz
	bsdtar -czf transfer.tar.gz \
		-s ",^\./,transfer/," \
		./appinfo \
		./COPYING \
		./img \
		./js \
		./lib \
		./l10n \
		./templates \
		./README.md

.PHONY: dist
dist: build archive

.PHONY: clean
clean:
	rm -rf node_modules js transfer.tar.gz
