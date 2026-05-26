RDX Exporter
============

이 PHP 스크립트는 Rhymix, XE1, 그누보드 등의 회원 정보와 게시판 데이터를 RDX (Rhymix Data eXchange) 형식으로 내보내는 기능을 제공합니다.
RDX는 JSON+ZIP 기반의 데이터 교환 형식으로, 한국형 커뮤니티에서 자주 사용되는 데이터 구조를 표현할 수 있도록 설계되어
기존의 XE 마이그레이션 툴보다 더 안정적인 데이터 이전을 지원합니다.

Rhymix에서 RDX를 들여오는 모듈은 2026년 여름에 공개할 예정입니다.

설치 환경
----------

- PHP 7.0 이상
- 확장 모듈: PDO, Zip, JSON
- 임시 파일을 저장할 수 있도록 웹 서버에 쓰기 권한과 충분한 디스크 공간이 필요합니다.

사용 방법
----------

1. 홈페이지 내 적당한 경로에 (예: `./rdx_exporter`) RDX Exporter를 업로드합니다.
2. RDX Exporter를 업로드한 디렉토리 아래에 `temp` 디렉토리를 생성하고, 쓰기 권한을 부여합니다.
3. RDX Exporter를 업로드한 디렉토리에 웹 브라우저로 접속합니다. (예: https://example.com/rdx_exporter/)
4. 사용중인 CMS의 종류의 설치 경로를 입력하고, 보안을 위해 DB 비밀번호를 입력합니다.
5. 내보내기할 데이터의 종류를 선택하고, "내보내기" 버튼을 클릭하면 zip 파일이 다운로드됩니다.
6. 내보내기하는 데 시간이 너무 오래 걸리거나 다운로드에 실패하는 경우, 내보내기 범위를 줄여서 다시 시도해 보세요. (TODO: 분할 다운로드 기능 지원 예정)
7. 작업을 마친 후에는 타인의 접속을 막기 위해 RDX Exporter를 삭제하는 것을 권장합니다. 특히 `temp` 디렉토리에 매우 큰 용량의 임시 파일이 남아 있을 수 있습니다.

RDX 아카이브 형식
----------------

RDX 아카이브 `zip` 파일 안에 `index.json` 및 다수의 JSONL 파일,
그리고 내보내기 옵션에 따라 각종 바이너리(이미지, 동영상, 문서파일 등의 첨부파일)을 포함할 수 있습니다.

JSONL은 각 줄이 하나의 JSON 객체로 구성된 데이터 레코드들을 나열한 텍스트 파일로,
많은 양의 데이터를 한꺼번에 메모리에 적재하지 않고 순차적으로 처리하는 데 특화된 형식입니다.

### index.json

`index.json` 파일은 아래와 같은 구조를 가집니다.

```
{
    "version": "1.0",
    "source": "Rhymix",
    "tz": "Asia/Seoul",
    "entries": [
        {
            "filename": "members/member_1.jsonl",
            "type": "member",
            "title": "",
            "range": "1-100"
        },
        {
            "filename": "messages/message_1.jsonl",
            "type": "message",
            "title": "",
            "range": "1-200"
        },
        {
            "filename": "boards/board_1.jsonl",
            "type": "board",
            "title": "자유게시판",
            "range": "1-1000"
        },
        {
            "filename": "boards/board_2.jsonl",
            "type": "board",
            "title": "자유게시판",
            "range": "1001-2000"
        },
        ... (후략) ...
    ]
}
```

| 최상위 속성 | 설명 |
|------|------------------|
| `version` | RDX 형식의 버전을 나타냅니다. 현재는 `1.0`이 유일한 버전입니다. |
| `source` | 원본 CMS의 종류를 나타내며, 들여오기할 때 참고할 수 있습니다. |
| `tz` | 원본 CMS에서 사용된 시간대입니다. 들여오기할 때 타임스탬프를 변환하는 데 사용됩니다. |
| `entries` | 아카이브에 포함된 JSONL 파일들의 목록입니다. 각 항목은 아래 테이블과 같은 속성을 가집니다. |

| entries 속성 | 설명 |
|------------------|------------------|
| `filename` | 압축된 파일의 경로입니다. RDX 아카이브 내에서 고유해야 합니다. |
| `type` | 데이터의 종류를 나타냅니다. 현재 지원하는 종류로는 `member`, `message`, `board`가 있습니다. |
| `title` | 데이터의 제목이나 설명입니다. 일반적으로 게시판 이름을 나타내는 데 사용됩니다. |
| `range` | 데이터의 범위를 나타냅니다. 예를 들어, `1-100`은 1번부터 100번까지의 레코드가 포함되어 있음을 의미합니다. 내보내기할 데이터의 양이 많은 경우, 동일한 종류의 데이터를 여러 파일에 나누어 저장할 수 있으며, 이 때 `range` 속성을 사용하여 각 파일에 포함된 데이터의 범위와 순서를 명시할 수 있습니다. |

### JSONL 파일

아카이브 내에서 JSONL 파일을 저장하는 경로에는 제한이 없습니다.
`index.json`에서 해당 경로를 명시하기만 하면 됩니다.
일반적으로는 데이터의 종류에 따라 디렉토리를 생성하여 구분하며, 필요하다면 다수의 하위 디렉토리를 생성하여도 무방합니다.

JSONL 파일은 여러 줄로 구성되어 있으며, 각 줄은 하나의 JSON 객체로 표현된 데이터 레코드를 나타냅니다.
줄바꿈 문자를 레코드 구분자로 사용하므로, 각 레코드의 내용에 포함된 줄바꿈 문자는 `\n`으로 이스케이프하여야 합니다.
PHP에서 `json_encode()` 함수를 기본값 그대로 사용하면 이 형식이 자동으로 적용됩니다.

JSONL 파일은 `index.json`에서 명시한 `type`과 일치하는 데이터 레코드만을 포함해야 합니다.

| Type | Description | Class |
|------|------------------|------------------|
| `member` | 회원 정보 | Rhymix\DataExchange\Models\Member |
| `message` | 쪽지 | Rhymix\DataExchange\Models\Message |
| `board` | 게시물 | Rhymix\DataExchange\Models\Document |

각 레코드는 해당 모델 클래스에서 선언한 속성만을 포함해야 합니다.
임의의 속성을 추가하거나, 속성을 누락할 경우 들여오기 시점에 오류가 발생할 수 있습니다.

### Member

| Name | Type | Required | Default | Description |
|------|------|----------|---------|-------------|
| `user_id` | `string` | 🗸 |  | 아이디 |
| `password` | `string` | 🗸 |  | 암호화된 비밀번호 |
| `user_name` | `string` |  |  | 이름 |
| `nick_name` | `string` | 🗸 |  | 닉네임 |
| `email_address` | `string` |  |  | 이메일 주소 |
| `phone_number` | `string` |  |  | 전화번호 (숫자만) |
| `phone_country` | `string` |  |  | 전화번호의 3자리 국가 코드 (예: KOR) |
| `phone_type` | `string` |  |  | 전화번호 종류: `mobile`, `home`, `work` 등 |
| `signup_date` | `date` | 🗸 |  | 가입일 |
| `signup_ipaddress` | `string` |  |  | 가입 IP 주소 |
| `last_login_date` | `date` |  |  | 마지막 로그인 일시 |
| `last_login_ipaddress` | `string` |  |  | 마지막 로그인 IP 주소 |
| `change_password_date` | `date` |  |  | 마지막 비밀번호 변경 일시 |
| `denied_until_date` | `date` |  |  | 로그인이 제한된 경우, 제한 해제 일시 |
| `is_admin` | `string` |  | `N` | 관리자 여부: `Y` 또는 `N` |
| `admin_description` | `string` |  |  | 관리용 설명 (비공개) |
| `status` | `string` |  | `APPROVED` | 상태: `APPROVED` 또는 `DENIED` |
| `homepage` | `string` |  |  | 홈페이지 URL |
| `blog` | `string` |  |  | 블로그 URL |
| `birthday` | `date` |  |  | 생년월일 |
| `profile_image` | `string` |  |  | 프로필 이미지 파일 (아래 참고) |
| `signature` | `string` |  |  | 서명 또는 자기소개 (공개) |
| `allow_mailing` | `string` |  | `Y` | 메일 수신 허용 여부: `Y` 또는 `N` |
| `allow_message` | `string` |  | `Y` | 쪽지 수신 허용 여부: `Y` 또는 `N` |
| `extra_vars` | `array` |  |  | 확장 변수 (key-value pair) |
| `groups` | `string[]` |  |  | 소속 그룹명 |
| `points` | `int` |  | 0 | 보유 포인트 |

- RDX 아카이브 내에서 회원을 참조할 때는 항상 `user_id`를 사용합니다.
  XE/Rhymix의 `member_srl`이나 각 테이블의 `auto_increment`와 같은 정수값은 데이터를 들여오는 사이트에서 그대로 유지된다는 보장이 없기 때문입니다.
- `user_id`는 중복되어서는 안 됩니다.
- 비밀번호는 일반적인 PHP CMS에서 사용하는 암호화 방식이라면 그대로 넣어도 무방합니다.
  Rhymix의 경우, bcrypt, pbkdf2, sha256, sha1, md5, MySQL `PASSWORD()` 등을 지원합니다.
- 원본 비밀번호를 알 수 없는 경우, 임의의 문자열을 md5 또는 sha1으로 암호화하여 넣는 것을 추천합니다. (사실상 로그인 금지)
- `profile_image`는 첨부파일의 `path`와 동일한 형식으로 합니다.
- 생년월일은 YYYYMMDD 형식으로 합니다.

### Message

| Name | Type | Required | Default | Description |
|------|------|----------|---------|-------------|
| `id` | `int` | 🗸 |  | 메시지 ID (고유값) |
| `title` | `string` |  |  | 제목 |
| `content` | `string` | 🗸 |  | 내용 |
| `sender_user_id` | `string` | 🗸 |  | 발신자의 아이디 |
| `recipient_user_id` | `string` | 🗸 |  | 수신자의 아이디 |
| `sent_date` | `date` | 🗸 |  | 발신 일시 |
| `read_date` | `date` |  |  | 읽음 일시 (읽지 않은 경우 `NULL`) |
| `ipaddress` | `string` |  |  | IP 주소 |
| `folder` | `string` | 🗸 | `Inbox` | 폴더: `Inbox` 또는 `Sent` |
| `references` | `int[]` |  |  | 참조 (다른 메시지를 참고하는 경우, 해당 메시지(들)의 ID값을 담는다) |
| `extra_vars` | `array` |  |  | 확장 변수 (key-value pair) |
| `files` | `File[]` |  |  | 첨부파일 |

- 쪽지에 제목이 없을 수도 있으므로, RDX 아카이브를 처리하는 프로그램은 이를 감안하여야 합니다.
  예를 들어 내용의 첫 줄을 복사하여 자동으로 제목을 생성할 수 있습니다.
- 보낸이와 받는이의 쪽지함에 모두 쪽지가 저장되어 있어야 하는 경우, 같은 내용으로 2개의 레코드를 생성하고 하나는 `Inbox`, 하나는 `Sent`에 넣습니다.
  단, 보낸이와 받는이 중 어느 한 쪽에서만 쪽지를 삭제한 경우에는 둘 중 하나가 존재하지 않을 수도 있습니다. (상대방의 쪽지함에서는 삭제되지 않는다고 가정합니다.)
- 쪽지에 파일이 첨부되어 있을 수 있으나, 들여오는 사이트에서 첨부파일을 지원한다는 보장은 없습니다.

### Document

| Name | Type | Required | Default | Description |
|------|------|----------|---------|-------------|
| `id` | `int` | 🗸 |  | 문서 ID (고유값) |
| `parent_id` | `int` |  |  | 상위 문서 ID (답글인 경우) |
| `category` | `string` |  |  | 카테고리명 |
| `lang_code` | `string` |  |  | 언어 코드 (예: `ko`) |
| `title` | `string` | 🗸 |  | 제목 |
| `content` | `string` | 🗸 |  | 내용 |
| `slug` | `string` |  |  | 슬러그 (블로그 등에서 짧은주소 생성에 사용) |
| `tags` | `string[]` |  |  | 태그 목록 |
| `read_count` | `int` |  | 0 | 조회 수 |
| `upvote_count` | `int` |  | 0 | 추천 수 |
| `downvote_count` | `int` |  | 0 | 비추천 수 |
| `comment_count` | `int` |  | 0 | 댓글 수 |
| `trackback_count` | `int` |  | 0 | 트랙백 수 |
| `file_count` | `int` |  | 0 | 첨부파일 수 |
| `regdate` | `date` | 🗸 |  | 등록일 |
| `last_update` | `date` |  |  | 마지막 수정일 |
| `ipaddress` | `string` |  |  | IP 주소 |
| `user_id` | `string` |  |  | 작성자 아이디 |
| `password` | `string` |  |  | 암호화된 비밀번호 (비회원 글인 경우) |
| `user_name` | `string` |  |  | 작성자 이름 |
| `nick_name` | `string` | 🗸 |  | 작성자 닉네임 |
| `email_address` | `string` |  |  | 작성자 이메일 주소 (비회원 글인 경우) |
| `homepage` | `string` |  |  | 작성자 홈페이지 URL (비회원 글인 경우) |
| `allow_comment` | `string` |  | `Y` | 댓글 허용 여부: `Y` 또는 `N` |
| `allow_trackback` | `string` |  | `Y` | 트랙백 허용 여부: `Y` 또는 `N` |
| `notify_message` | `string` |  | `N` | 알림 메시지 여부: `Y` 또는 `N` |
| `is_notice` | `string` |  | `N` | 공지 여부: `Y` 또는 `N` 또는 `A` |
| `title_bold` | `string` |  | `N` | 제목 굵게 여부: `Y` 또는 `N` |
| `title_color` | `string` |  |  | 제목 색상: `#RRGGBB` |
| `status` | `string` |  | `PUBLIC` | 상태: `PUBLIC` 또는 `SECRET` |
| `extra_vars` | `array` |  |  | 확장 변수 (key-value pair) |
| `comments` | `Comment[]` |  |  | 댓글 (`Comment` 모델 인스턴스의 목록) |
| `files` | `File[]` |  |  | 첨부파일 (`File` 모델 인스턴스의 목록) |
| `links` | `string[]` |  |  | 링크 (확장변수 외에 따로 추가한 경우에 한함) |

- 들여오는 사이트에서 답글을 지원하지 않을 수 있습니다.
- 언어 구분이 없는 경우, 언어 코드를 임의로 생성하지 말고 `NULL`로 둡니다.
- 조회 수, 추천 수, 댓글 수 등 모든 `count` 속성의 기본값은 0입니다.
- 비회원이 작성한 글인 경우 `user_id`를 `NULL`로 두고, 비회원이 입력한 이름을 `nick_name`에 넣습니다.
- 전체공지는 `A`로 표시합니다.
- 글에 링크를 첨부할 수 있으나, 들여오는 사이트에서 링크를 지원하지 않는 경우 본문에 추가되거나 확장변수로 처리될 수 있다고 가정하여야 합니다.

### Comment

| Name | Type | Required | Default | Description |
|------|------|----------|---------|-------------|
| `id` | `int` | 🗸 |  | 댓글 ID (고유값) |
| `parent_id` | `int` |  |  | 부모 댓글 ID (대댓글인 경우) |
| `content` | `string` | 🗸 |  | 내용 |
| `upvote_count` | `int` |  | 0 | 추천 수 |
| `downvote_count` | `int` |  | 0 | 비추천 수 |
| `comment_count` | `int` |  | 0 | 댓글 수 |
| `trackback_count` | `int` |  | 0 | 트랙백 수 |
| `file_count` | `int` |  | 0 | 첨부파일 수 |
| `regdate` | `date` | 🗸 |  | 등록일 |
| `last_update` | `date` |  |  | 마지막 수정일 |
| `ipaddress` | `string` |  |  | IP 주소 |
| `user_id` | `string` |  |  | 작성자 아이디 |
| `password` | `string` |  |  | 암호화된 비밀번호 (비회원 글인 경우) |
| `user_name` | `string` |  |  | 작성자 이름 |
| `nick_name` | `string` | 🗸 |  | 작성자 닉네임 |
| `email_address` | `string` |  |  | 작성자 이메일 주소 (비회원 글인 경우) |
| `homepage` | `string` |  |  | 작성자 홈페이지 URL (비회원 글인 경우) |
| `notify_message` | `string` |  | `N` | 알림 메시지 여부: `Y` 또는 `N` |
| `status` | `string` |  | `PUBLIC` | 상태: `PUBLIC` 또는 `SECRET` |
| `extra_vars` | `array` |  |  | 확장 변수 (key-value pair) |
| `files` | `File[]` |  |  | 첨부파일 (`File` 모델 인스턴스의 목록) |

- 들여오는 사이트에서 대댓글을 지원하지 않을 수 있습니다.
- 조회 수, 추천 수, 댓글 수 등 모든 `count` 속성의 기본값은 0입니다.
- 비회원이 작성한 글인 경우 `user_id`를 `NULL`로 두고, 비회원이 입력한 이름을 `nick_name`에 넣습니다.
- 댓글에 파일을 첨부하거나 확장변수를 지정할 수 있으나, 들여오는 사이트에서 지원하지 않을 수도 있다고 가정하여야 합니다.

### File

| Name | Type | Required | Default | Description |
|------|------|----------|---------|-------------|
| `id` | `int` | 🗸 |  | 파일 ID (고유값) |
| `filename` | `string` | 🗸 |  | 원본 파일명 (예: example.jpg) |
| `path` | `string` | 🗸 |  | 실제 파일이 저장된 경로 (아래 참고) |
| `url` | `string` | 🗸 |  | CMS 설치 경로를 기준으로 한 URL (아래 참고) |
| `download_count` | `int` |  | 0 | 다운로드 수 |
| `regdate` | `date` | 🗸 |  | 등록일 |
| `ipaddress` | `string` |  |  | IP 주소 |
| `file_size` | `int` | 🗸 |  | 파일 크기 |
| `mime_type` | `string` |  |  | MIME 타입 |
| `original_type` | `string` |  |  | 변환된 파일인 경우, 원본의 타입 |
| `width` | `int` |  |  | 이미지 너비 (px) |
| `height` | `int` |  |  | 이미지 높이 (px) |
| `duration` | `int` |  |  | 동영상/오디오 길이 (초) |
| `is_valid` | `string` |  | `Y` | 유효 여부: `Y` 또는 `N` |
| `is_cover_image` | `string` |  | `N` | 대표 이미지 여부: `Y` 또는 `N` |
| `comment` | `string` |  |  | 관리용 설명 (비공개) |
| `extra_vars` | `array` |  |  | 확장 변수 (key-value pair) |

- MIME 타입, 이미지 및 동영상 속성 등을 임의로 입력하기보다는 들여오는 사이트의 판단에 맡기는 것을 권장합니다.
- 관리용 설명과 확장변수는 들여오는 사이트에서 지원하지 않을 수 있습니다.

### 공통

- 각 레코드의 ID는 하나의 RDX 아카이브 내에서 상호 참조를 위해 사용하는 값으로, 들여오기할 때 해당 ID가 그대로 유지된다는 보장은 없습니다.
- 필수가 아니고 값이 없는 속성이라도 존재해야 합니다. 속성을 삭제해서는 안 됩니다.
- `int` 타입의 속성에 값이 없는 경우에는 `0`을 넣습니다.
- `string` 타입의 속성에 값이 없는 경우에는 `NULL`을 넣습니다. 단, `Y`/`N`과 같이 특정한 형식의 문자열이 요구되는 경우에는 해당 형식에 맞는 값을 넣어야 합니다.
- 배열 타입의 속성에 값이 없는 경우에는 빈 배열을 넣습니다. `NULL`로 치환하지 않습니다.
- `date`는 `YmdHis` 형식으로 14바이트여야 하며, 해당 형식에 맞는 데이터를 넣을 수 없는 경우 `NULL`을 넣습니다.
  생일 등 특별히 허용되는 속성이 아니라면 8자리 날짜(`Ymd`)만 입력하는 것은 허용하지 않습니다.
- `Comment`와 `File`은 다른 클래스의 속성으로 들어갈 뿐, 데이터 레코드의 최상위 계층으로 등장하지 않습니다.
- 확장변수는 key-value pair로 표현된 JSON 객체로, key는 문자열이어야 하며 value는 문자열, 숫자, 불리언, list 또는 `NULL`이 될 수 있습니다.
  기본적으로 Rhymix에서 array로 취급하는 확장변수는 모두 list로 인코딩합니다.
  nested object 또는 associative array를 value에 넣는 것은 허용하지 않습니다.

### 첨부파일 처리 방법

첨부파일은 필요에 따라 아래의 2가지 방법 중 하나를 사용하여 입력합니다.

#### 아카이브에 포함

첨부파일을 RDX 아카이브에 포함하면 해당 아카이브 파일 외에 추가적인 파일 관리가 필요하지 않으므로 가장 간편한 방법입니다.
단, 첨부파일이 많은 경우 아카이브의 용량이 매우 커질 수 있고, 다운로드 및 들여오기 시점에 시간 초과 또는 용량 초과로 오류가 날 수 있습니다.

아카이브에 포함된 파일의 `path` 속성은 아래와 같은 형식으로 아카이브 내의 파일 경로를 참조합니다.

```
"path":"rdx:files/attach/.../example.jpg",
"url":"files/attach/.../example.jpg",
```

아카이브 내의 파일 경로는 임의로 정할 수 있으므로, `path`와 `url`이 서로 유사한 형식을 띨 필요는 없습니다.
그러나 기본 제공되는 내보내기 드라이버들은 모두 편의상 원본 파일의 파일시스템 경로를 그대로 사용하도록 되어 있습니다.
또한 `url`의 경우, 게시물 본문에 해당 이미지 경로를 삽입해 놓은 경우가 많으므로
링크가 깨지는 것을 막기 위해 가능하면 원본의 디렉토리 구조를 그대로 유지할 것을 권장합니다.

#### 파일시스템 경로 참조

첨부파일을 RDX 아카이브에 포함하지 않고 파일시스템 경로를 참조하는 방법도 지원합니다.
이렇게 하면 아카이브 파일의 용량과 다운로드/들여오기 시간을 줄일 수 있지만, 첨부파일이 포함된 디렉토리를 별도로 보관하고 업로드하여야 합니다.

이 경우, `path` 속성은 아래와 같은 형식으로 **CMS 설치 디렉토리에 대하여 상대적인** 파일시스템 경로를 참조합니다.]

```
"path":"url:files/attach/.../example.jpg",
"url":"files/attach/.../example.jpg",
```

절대경로나 실제 URL을 입력하지 않는 이유는, 서버 이전에 따른 경로 및 홈페이지 주소 변경에 유연하게 대응하기 위함입니다.
이러한 상대경로를 기준으로 실제 경로를 다시 생성하는 것은 RDX 아카이브를 들여오기하는 프로그램의 몫입니다.

RDX Reader
----------------

첨부된 RDX Reader 클래스를 사용하여 RDX 아카이브에 포함된 회원 정보, 쪽지, 게시물 등을 추출할 수 있습니다.
이것을 활용하여 다양한 CMS에서 RDX 아카이브를 들여오는 프로그램을 개발할 수 있습니다.

```
<?php

include 'libraries/RDXReader.php';
$reader = new Rhymix\DataExchange\Libraries\RDXReader('example.zip');
foreach ($reader->getIndexEntries() as $entry)
{
    echo "Type: {$entry['type']}, Title: {$entry['title']}, Range: {$entry['range']}\n";
}
foreach ($reader->getMembers() as $member)
{
    echo "User ID: {$member->user_id}, Name: {$member->nick_name}\n";
}
foreach ($reader->getMessages() as $message)
{
    echo "Message ID: {$message->id}, Title: {$message->title}\n";
}
foreach ($reader->getDocuments('자유게시판', 0, 10) as $document)
{
    echo "Document ID: {$document->id}, Title: {$document->title}\n";
}
```

회원 정보, 쪽지, 게시물을 읽어오는 메소드는 `Generator`를 반환하므로,
많은 양의 데이터를 처리하더라도 `foreach` 루프를 사용하여 한 레코드씩 순차적으로 처리할 수 있습니다.

게시물의 경우, `offset`과 `count` 매개변수를 사용하여 특정 범위의 게시물만 추출할 수도 있으나,
순차적으로 읽어야 하는 JSONL 포맷의 특성상 효율적이지 않으니 참고하시기 바랍니다.
