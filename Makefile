NODE_IMAGE ?= docker.io/library/node:20

.PHONY: build
build:
	podman run --rm -v .:/app:z -w /app $(NODE_IMAGE) sh -c "npm ci && npm run build"

.PHONY: dist
dist: build
	rm -f transfer.tar.gz
	bsdtar -czf transfer.tar.gz \
		-s ",^\./,transfer/," \
		./appinfo \
		./COPYING \
		./img \
		./js \
		./lib \
		./l10n \
		./README.md

.PHONY: clean
clean:
	rm -rf node_modules js transfer.tar.gz
