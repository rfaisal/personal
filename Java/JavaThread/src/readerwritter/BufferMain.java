package readerwritter;

public class BufferMain {

	/**
	 * @param args
	 * @throws InterruptedException 
	 */
	public static void main(String[] args) throws InterruptedException {
		Buffer buffer=new Buffer(100);
		Thread t=new Thread(new BufferReader(buffer));
		t.start();
		new Thread(new BufferWritter(buffer)).start();
		t.join();
	}

}
