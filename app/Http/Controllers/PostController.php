<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Models\Post;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;



class PostController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $posts = Post::with('user')->latest()->paginate(4);

        return view('posts.index', compact('posts'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('posts.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePostRequest $request)
    {
        $post = new Post($request->all());
        $post->user_id = $request->user()->id;

        $file = $request->file('image');
        $post->image = self::createFileName($file);

        DB::beginTransaction();
        try {
            // toroku
            $post->save();

            // gazou
            if (!Storage::putFileAs('images/posts', $file, $post->image)) {
                //reigai
                throw new \Exception('画像ファイルの保存に失敗しました。');
            }
            // toranzakusyon
            DB::commit();
        } catch (\Exception $e) {
            // sippai
            DB::rollback();
            return back()->withInput()->withErrors($e->getMessage());
        }

        return redirect()
            ->route('posts.show', $post)
            ->with('notice', '記事を登録しました');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $post = Post::with(['user'])->find($id);
        $comments = $post->comments()->latest()->get()->load(['user']);

        return view('posts.show', compact('post', 'comments'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $post = Post::find($id);

        return view('posts.edit', compact('post'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePostRequest $request, string $id)
    {
        $post = Post::find($id);

        if ($request->user()->cannot('update', $post)) {
            return redirect()->route('posts.show', $post)
                ->withErrors('自分の記事以外は更新できません');
        }

        $file = $request->file('image');
        if ($file) {
            $delete_file_path = $post->image_path;
            $post->image = self::createFileName($file);
        }
        $post->fill($request->all());

        // toranzakusyonkaisi
        DB::beginTransaction();
        try {

            $post->save();

            if ($file) {
                // gazou
                if (!Storage::putFileAs('images/posts', $file, $post->image)) {
                    // ro-rubakku
                    throw new \Exception('画像ファイルの保存に失敗しました');
                }

                // gazousakujo
                if (!Storage::delete($delete_file_path)) {
                    // gazousakujo
                    Storage::delete($post->image_path);
                    // reigai
                    throw new \Exception('画像のファイルの削除に失敗しました。');
                }
            }

            // toranzakusyon
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            return back()->withInput()->withErrors($e->getMessage());
        }

        return redirect()->route('posts.show', $post)
            ->with('notice', '記事を更新しました。');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $post = Post::find($id);

        // torannzakusyon
        try {
            $post->delete();

            // gazou
            if (!Storage::delete($post->image_path)) {
                throw new \Exception('画像ファイルの削除に失敗しました。');
            }

            // toranzakkusyon  syuuryou
            DB::commit();
        } catch (\Exception $e) {
            // sippai
            DB::rollBack();
            return back()->withErrors($e->getMessage());
        }

        return redirect()->route('posts.index')
            ->with('notice', '記事を削除しました');
    }

    private static function createFileName($file)
    {
        return date('YmdHis') . '_' . $file->getClientOriginalName();
    }
}
